---
paths:
  - "app/Modules/Caixa/**"
---

# Modulo: Caixa

Caixa diario por empresa: abrir/fechar/reabrir, sangria/reforco, e o motor de baixas
(`darBaixaParcelaPagamento` / `darBaixaParcelaDespesa`) e estorno consumido por Pagamento, Despesa e
Venda. Aqui tambem vivem os models `BaixaPagamento` e `BaixaDespesa`. O antigo `MovimentoCaixa` foi
removido: toda entrada/saida de dinheiro agora e um `Lancamento` numa `Conta` (ADR-0010, motor abaixo).
O caixa diario virou a **sessao da conta-caixa** (`caixas.conta_id` aponta para a conta
`eh_caixa_padrao`). Ver `.claude/rules/modulos/conta.md`.

## Entidades & status
- `Caixa` (tabela `caixas`): `conta_id` (FK -> conta `eh_caixa_padrao`), `data`, `saldo_abertura`,
  `saldo_fechamento`, `status` (enum `StatusCaixa`: `Aberto`, `Fechado`), `observacao`, `fechado_em`,
  `fechado_por`, `usuario_id`. BaseModel + EmpresaTrait (isolado por empresa). Relacoes `usuario`,
  `fechadoPor`, `conta`, `lancamentos`. `saldo_abertura`/`saldo_fechamento` sao contagem FISICA da
  gaveta (reconciliacao) — **nao** viram lancamento. `saldoCalculado()` agrega os `lancamentos` da
  sessao por `caixa_id`.
- `Lancamento` (tabela `lancamentos`; BaseModel + EmpresaTrait, append-only) mora no modulo Conta:
  credito/debito numa `Conta`, `caixa_id` nullable (setado so nos lancamentos da conta-caixa),
  `categoria` (`movimento`/`sangria`/`reforco`/`estorno`), origem por FK `baixa_pagamento_id` /
  `baixa_despesa_id`. Ver `.claude/rules/modulos/conta.md`.
- `BaixaPagamento` / `BaixaDespesa` (BaseModel + EmpresaTrait): `valor`, `multa`, `juros`, `desconto`,
  `forma_pagamento_id` (FK `formas_pagamento`) + `forma_pagamento_nome` (snapshot), `data`,
  `estornado_em` (nullable; marcador de estorno — ADR-0011), FK `caixa_id` nullable. `valorTotal()` =
  principal + multa + juros − desconto. BaixaDespesa tem SoftDeletes; BaixaPagamento nao. **Regime "fluxo,
  nao saldo" (ADR-0011): a Baixa E o registro do recebimento por forma** — so a baixa da **gaveta**
  (dinheiro) gera `Lancamento`; cartao/pix/boleto/crediario/banco registram so a Baixa (sem lancamento,
  sem recebivel).
- `Recebivel` (tabela `recebiveis`; BaseModel + EmpresaTrait + SoftDeletes): **DORMENTE** — o ADR-0011
  aposentou os recebiveis (nao sao mais gerados; some "a cair / disponivel / data prevista"). Model,
  tabela, enum `StatusRecebivel` e scopes ainda existem, mas **sem escrita**; removidos na Fatia 2. Ver
  ADR-0011 e `.claude/rules/modulos/forma-pagamento.md`.

## Camadas-chave
- `CaixaService`:
  - `caixaAberto()` / `caixaDoDia(string $data)` — buscam pela empresa em contexto (global scope da
    EmpresaTrait); NAO filtram empresa_id na mao.
  - `abrir(saldo, data, ?obs)` — lanca `NegocioException` se ja existe caixa NA DATA (regra "1 por
    empresa/dia" e validada em codigo, NAO ha unique no schema). `fechar`, `reabrir` (so caixa
    Fechado; loga activity), `registrarSangria`, `registrarReforco`.
  - `darBaixaParcelaPagamento(..., ?int $parcelasCartao)` (credito) / `darBaixaParcelaDespesa(...)`
    (debito). Ambos delegam ao template privado `aplicarBaixaParcela(FormaPagamento $forma, ...)`:
    valida, cria a Baixa (com `forma_pagamento_id` + `forma_pagamento_nome`), soma `valor_pago`, marca
    Pago, `recalcularStatus()`. **Eixo de decisao (ADR-0011) = a conta destino e do tipo Caixa?**
    (`resolverContaDestino(forma, empresaId)`): **Caixa (gaveta/dinheiro)** EXIGE caixa aberto e grava
    UM `Lancamento` (credito no recebimento / debito na despesa) com `caixa_id`; **qualquer outra conta**
    (cartao/pix/boleto/crediario/banco) registra **so a Baixa** — sem lancamento, sem recebivel, sem
    exigir caixa. Regra unica de caixa: exige caixa aberto ⟺ `conta.tipo === caixa`. `$parcelasCartao` e
    aceito por compat (ignorado; some na Fatia 2). Tudo em `DB::transaction`.
  - `estornarPagamento(Pagamento)` — parcelas Pendente -> Cancelado; titulo -> `StatusPagamento::Estornado`.
    Marca cada baixa com **`estornado_em`** (marcador unico que o painel do dia neta). **So a baixa da
    gaveta** tem `Lancamento` a reverter: contra-lancamento de debito (`categoria = estorno`) na mesma
    conta/caixa (bloqueia se o caixa de origem estiver fechado). Cartao/pix/banco nao tem lancamento —
    nada a reverter, so a marca.
- `CaixaController` — `index` (navegacao por `?data=YYYY-MM-DD`, calcula totais/saldo do dia +
  passa o `$resumo` do dia por forma), `store` (abrir), `show` (redirect p/ index na data), `fechar`,
  `reabrir`, `sangria`, `reforco`.
- `ResumoDiaService` (leitura) — **`porForma(string $dia)`**: panorama do dia por forma de pagamento,
  no regime "**quando o cliente pagou**" (a baixa), NAO a liquidacao. Fonte unica = `BaixaPagamento`
  (`whereDate('data',$dia)` group by `forma_pagamento_nome`, Σ bruto `valorTotal`); **estornado** = baixas
  com `whereDate('estornado_em',$dia)` (valuadas pelo BRUTO da baixa, netam exato — ADR-0011);
  **liquido** = recebido − estornado, por forma e total. **Eixo DISJUNTO
  do saldo da gaveta** (informativo; NAO entra no `saldoCalculado`, que segue so `caixa->lancamentos`).
  Tenancy pela EmpresaTrait (a tela ja resolve a empresa unica). Card na `index.blade.php` acima de
  "Movimentos", com nota distinguindo os eixos.
- DTOs `AbrirCaixaData`, `FecharCaixaData`, `MovimentoCaixaData`, `ReabrirCaixaData`. Requests
  homonimos. Policy `CaixaPolicy` (permissoes **`financeiro.ver/criar/editar`** — note o prefixo
  `financeiro`, nao `caixa`).

## Regras de negocio / gotchas
- Saldo do dia = `saldo_abertura + Σ lancamentos da sessao` (creditos − debitos), agregado por
  `caixa_id` via `Caixa::saldoCalculado()` / `CaixaController::index`. Reforco entra como credito,
  sangria como debito. `saldo_abertura`/`saldo_fechamento` sao contagem fisica (nao viram lancamento).
- Sangria/reforco (`registrarSangria`/`registrarReforco`) criam um `Lancamento` (`categoria`
  `sangria`/`reforco`) na conta-caixa da sessao, ligado por `caixa_id`.
- Caixa Diario opera em UMA empresa: se ha multiplas em `empresas_atuais` sem contexto, o `index`
  escolhe a primeira silenciosamente (seta `empresa_contexto_atual`) para nao travar a tela.
- Reabrir limpa `saldo_fechamento`/`fechado_em`/`fechado_por` e acrescenta nota de auditoria na
  observacao. So caixas Fechados; senao `NegocioException`.
- `BaixaPagamento`/`BaixaDespesa` tem `empresa_id` NOT NULL — por isso os controllers de
  Pagamento/Despesa envolvem a baixa em `comEmpresaDeCriacao($parcela->empresa_id, ...)`.
- Toda escrita de caixa/baixa vive em transacao no Service; nunca usar `DB::` no controller.

## Veja tambem
- `.claude/rules/modulos/conta.md` (razao unificado: `Conta` + `Lancamento`, ADR-0010).
- `.claude/rules/modelo-financeiro.md` (fluxo de baixa, estorno, enums).
- `.claude/rules/multi-tenant-seguranca.md` (Caixa = empresa-level; defesa em profundidade na escrita).
