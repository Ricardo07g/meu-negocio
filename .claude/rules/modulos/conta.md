---
paths:
  - "app/Modules/Conta/**"
---

# Modulo: Conta (razao unificado)

Onde o dinheiro da empresa fica e o razao de lancamentos (credito/debito). Transacional
(empresa-level). Toda entrada/saida de dinheiro e um `Lancamento` numa `Conta` — o `MovimentoCaixa`
foi removido (ver ADR-0010 e `.claude/rules/modelo-financeiro.md`).

## Entidades & status
- **`Conta`** (tabela `contas`; BaseModel + EmpresaTrait + SoftDeletes): onde o dinheiro fica.
  `tipo` (enum `TipoConta`: `caixa`/`banco`/`carteira`), `nome`, `saldo_inicial`, `ativo`, flags
  `eh_caixa_padrao` (a gaveta) e `eh_destino_recebivel_padrao` (destino padrao dos recebiveis),
  `instituicao`/`agencia`/`numero` (rotulo, so banco/carteira). `saldo()` = `saldo_inicial + Σcredito
  − Σdebito` (ADR-0011 — sem recebiveis; so a gaveta acumula lancamentos na pratica, banco/carteira sao
  **rotulos de origem** sem saldo vivo). Scope `ativas()`. Guards de trilho:
  `ehProtegida()` (⟺ `tipo === Caixa`), `temMovimentacoes()` (lancamentos/recebiveis),
  `estaEmUso()` (forma/caixa aponta), `temFormaAtivaVinculada()`, `podeExcluir()`.
- **`Lancamento`** (tabela `lancamentos`; BaseModel + EmpresaTrait, **append-only, sem SoftDeletes**):
  toda entrada (credito) ou saida (debito). Campos: `conta_id` (obrigatorio), `caixa_id` (nullable —
  setado so nos lancamentos da conta-caixa, ligando a sessao da gaveta), `tipo` (enum
  `TipoLancamento`: `credito`/`debito`; `sinal()`/`cor()`), `categoria` (string:
  `movimento`/`sangria`/`reforco`/`estorno`; `transferencia`/`ajuste`/`abertura` reservados),
  `valor`, `data` (date), `descricao`, `forma_pagamento_nome` (snapshot), origem por FK
  (`baixa_pagamento_id`/`baixa_despesa_id`).

## Camadas-chave
- `ContaController` — resource CRUD (except `show`) + **`extrato(Request, Conta)`** (rota
  `GET contas/{conta}/extrato`; filtra por **mês** `?mes=YYYY-MM`, default mês atual, com nav
  prev/próximo e totais do mês) + **`exportar`** (`POST contas/{conta}/exportar`) + **`baixarExportacao`**
  (`GET contas/{conta}/exportacoes/{exportacao}/baixar`) + **`inativar`/`reativar`** (`PATCH ...`,
  `authorize('update')`). `ContaPolicy` (`conta.ver/criar/editar/excluir`; `view` no extrato/exportar/
  baixar; `update` no inativar/reativar) registrada em `AppServiceProvider`. `update` roteia a Caixa
  protegida para `renomear` (o DTO nao e montado — a request so valida `nome`).
- **Exportacao de extrato (assincrona, ADR-0012):** model `Exportacao` (`exportacoes`; BaseModel +
  EmpresaTrait; `status` enum `StatusExportacao` processando/pronto/erro, `formato` enum
  `FormatoExportacao` csv/xlsx, `periodo_inicio/fim`, `caminho`/`disco`). `ExportacaoService::solicitar`
  cria o pedido (rede/empresa explicitos da conta) e enfileira o Job `GerarExportacaoExtrato` (fila
  `database`). O Job resolve tudo com `withoutGlobalScopes()` (worker sem auth/session), gera a planilha
  via `EscritorExtrato` (openspout, CSV/XLSX em streaming/chunks) e grava num path PRIVADO no R2; o
  download e **autenticado** (`Storage::download`, nunca URL publica). Tela: card "Exportar periodo" +
  lista "Exportacoes recentes". **Worker:** serviço `queue` no docker-compose (`queue:work database`).
  - **Retencao:** `Exportacao::DIAS_RETENCAO` (1 dia) + `expiraEm()`. Comando `exportacoes:limpar`
    (`LimparExportacoes`, agendado **hourly** em `routes/console.php`, `withoutGlobalScopes`) apaga
    arquivo no storage + registro dos expirados. Exclusao manual: `DELETE contas/{conta}/exportacoes/
    {exportacao}` (`excluirExportacao`; 404 cross-conta). Job faz `find` (nao `findOrFail`) — se a
    exportacao foi excluida antes do worker pegar, sai sem erro.
  - **Status/AJAX:** `GET .../exportacoes/status` (`exportacoesStatus`, JSON) alimenta o **polling** da
    tela (sem reload): atualiza badges, "Expira" e libera "Baixar"; para sozinho quando nada processa.
    O JS re-liga o `data-confirm` (SweetAlert) nas linhas que injeta (o handler do layout so pega as do load).
- `ContaService` — `listar` + `criar` (rejeita `tipo=Caixa`) + `renomear` + `atualizar` (preserva as
  flags internas; so nome/tipo/saldo/ativo/banco) + `excluir` (guards) + `inativar`/`reativar` +
  **`semearPadrao(redeId, empresaId)`** (cria conta Caixa + Conta Bancaria). Chamado por
  `CriarEmpresaAction` (contas ANTES das formas), `DesenvolvimentoSeeder` e `CriaTenant`.
- **Quem grava `Lancamento`:** o `CaixaService` (nao o ContaService). Ver `.claude/rules/modulos/caixa.md`
  (`resolverContaDestino`/`resolverContaCaixa`/`aplicarBaixaParcela`/`estornarPagamento`/sangria/reforco).
- `DashboardService` — `saldoPorConta()` (card "Contas": so a gaveta mostra saldo; banco/carteira sao
  rotulos — ADR-0011 removeu o card "Recebiveis a cair").

## Regras de negocio / gotchas
- **Trilhos da Conta (ADR-0010):** a conta **Caixa e do sistema** — 1 por empresa (nasce no seed),
  nao muda de tipo, nao inativa nem exclui; **so renomeia**. O lojista so cria/edita `Banco`/`Carteira`
  (o form nao oferta `tipo=Caixa`; a `SalvarContaRequest` restringe `tipo` a Banco/Carteira e, ao editar
  a Caixa, valida so `nome`). As flags `eh_caixa_padrao`/`eh_destino_recebivel_padrao` sao **internas**
  (so o seed marca) — sairam do form. **Excluir** so com 0 movimentacoes **e** sem vinculo (forma/caixa);
  senao **inativar** (mesma trilha de `PerfilAcessoService::excluir`, surfada por `TratamentoErros`).
  Nao se inativa a Caixa nem conta com forma ATIVA vinculada (troque o destino da forma antes).
- **Regra do lancamento (ADR-0011):** so a baixa da **gaveta** (dinheiro) gera `Lancamento` — cartao/pix/
  boleto/crediario/banco registram so a `BaixaPagamento` (fluxo, nao saldo). `Recebivel` esta dormente
  (nao e mais escrito). O saldo de banco nao e controlado; a gaveta e o unico saldo vivo.
- **Tenancy:** `Lancamento`/`Recebivel` NAO tem rota/controller (uso interno via CaixaService) — sem
  Policy propria. Escritas passam `rede_id`/`empresa_id` explicitos (da parcela/baixa/caixa). Resolucao
  de conta e empresa-explicita (`withoutGlobalScope('empresa') + where empresa_id`, scope de rede ativo).
- `saldo_abertura`/`saldo_fechamento` do `Caixa` sao contagem fisica da gaveta (reconciliacao) —
  **nao** viram lancamento (evita dupla contagem). O saldo da sessao vem do razao (`caixa->lancamentos`).

## Veja tambem
- ADR-0010 (`docs/ADR/`) — razao unificado; **ADR-0011** — "fluxo, nao saldo" (aposenta recebiveis e
  saldo de banco; so a gaveta tem saldo vivo); **ADR-0012** — exportacoes assincronas (fila + download
  autenticado; extrato por mes + planilha por periodo via job).
- `.claude/rules/modelo-financeiro.md`, `modulos/caixa.md`, `modulos/forma-pagamento.md`.
- `.claude/rules/multi-tenant-seguranca.md` (Conta + Lancamento = transacional empresa-level).
