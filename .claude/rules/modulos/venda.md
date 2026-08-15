---
paths:
  - "app/Modules/Venda/**"
---

# Modulo: Venda

Frente de venda transacional (com `empresa_id`). **Tres tipos**: servico unico, servico em etapas
(`VendaEtapas`) e produtos (`VendaProduto` + carrinho multi-item). Cada venda gera um `Pagamento`
(titulo + parcelas). **Nao existe mais "venda pacote"** — foi renomeado para `VendaEtapas`.

## Entidades & status
- **`VendaEtapas`** (`vendas_etapas`): BaseModel + EmpresaTrait, RegistraAtividade, SoftDeletes.
  Campos: `cliente_id`, `servico_id`, `atendente_id`, `data` (1a sessao), `valor_total`, `desconto`,
  `acrescimo`, `qtd_etapas`, `status`, `observacao`. Relacoes: `agendamentos` (HasMany via
  `venda_etapas_id`), `pagamento` (HasOne). Metodos `etapasRealizadas()`/`etapasPendentes()` contam
  agendamentos por status.
  Enum **`StatusVendaEtapas`**: `Ativo`, `Concluido`, `Cancelado` (label/cor success/primary/danger).
- **`VendaProduto`** (`vendas_produto`): mesmos traits. Campos: `cliente_id` (nullable),
  `usuario_id` (vendedor), `data`, `subtotal`, `desconto`, `acrescimo`, `valor_total`, `status`,
  `observacao`. Relacoes: `itens` (HasMany `VendaProdutoItem`), `pagamento` (HasOne).
  Enum **`StatusVendaProduto`**: `Ativa`, `Cancelada`.
- **`VendaProdutoItem`** (`venda_produto_itens`): `Model` direto (NAO BaseModel — sem tenancy
  proprio, herda da venda). Campos: `produto_id`, `descricao`, `quantidade`, `valor_unitario`,
  `desconto`, `acrescimo`, `subtotal`.
- **Servico unico nao tem model proprio** — a venda "unica" e apenas 1 `Agendamento` (FK
  `venda_etapas_id` NULL) + 1 `Pagamento` (FK `agendamento_id`). Listagem agrega os 3 tipos.
  **So e venda o agendamento que TEM titulo** (`whereHas('pagamento')` em `listar`) — agendamento
  solto e agenda, nao faturamento (ADR-0018).

## Camadas-chave
- **`VendaController`**: `store` -> `processarVenda` roteia por `tipo_venda`/`servico->isEtapas()`.
  Escrita envolta em `comEmpresaDeCriacao($empresaId, fn ...)` (trait `DefineEmpresaDeCriacao`).
  Cancelar e excluir por tipo: `*Unico` / `*Etapas` / `*Produto`; `recibo($tipo,$id)` gera PDF;
  `show($tipo,$id)` = detalhes. **Nao existe edicao de venda** (sem rota/metodo edit|update).
- **`VendaService`**: `criarUnico` (pre-pago: cria agendamento + titulo), **`cobrarAtendimento`**
  (agendamento que ja existe: cria titulo + finaliza; recusa titulo duplicado), `criarEtapas`,
  `criarVendaProduto` (cada um em `DB::transaction`);
  `cancelar*` (estorno); `excluir*`; `detalhar($tipo,$id)`;
  `listar` (merge dos 3 tipos + paginacao manual com filtros pesados). O `listar` faz eager de
  `pagamento.parcelas.baixas` — a forma/parcelamento mora na BAIXA, e o card da listagem os exibe.
- **Actions**: `VenderEtapasAction` (cria VendaEtapas + N Agendamentos, **detecta conflito por
  sessao** e acumula em `ConflitoAgendamentoException` com lista de datas) e
  `CriarVendaProdutoAction` (cria venda + itens, baixa estoque, cria Pagamento).
- **DTOs/Requests**: `VenderEtapasData` (cliente/servico/atendente, `valor_total`, `horario`,
  `datas[]`, `horarios[]?`). `CriarVendaRequest` — **o unico Request do modulo** (ramifica por
  `tipo_venda` produto/servico e por `isEtapas`). `RecebimentoData` = 1 linha do split de formas
  (`forma`, `valor`, `parcelas_cartao`).
- **`VendaEtapasPolicy`**: usa permissoes do **agendamento** (`agendamento.ver|criar|cancelar`), nao
  `venda.*`. Metodos `viewAny/view/create/cancel`.

## Regras de negocio / gotchas
- A vista pode exigir caixa aberto: `processarVenda` checa `caixaService->exigeCaixaAberto($forma)`
  ANTES da transacao (so quando a forma e imediata e a conta destino e do tipo caixa — dinheiro sim;
  pix-direto/cartao nao). A baixa automatica e `VendaService::baixarAVistaSeAplicavel` ->
  `CaixaService::darBaixaParcelaPagamento` (so quando `AVista` E forma informada).
- **Split de formas**: a venda aceita N `recebimentos` (formas distintas), cuja soma tem de bater com
  o total. Cada linha vira uma Baixa na parcela unica. `parcelas_cartao` (2x, 3x...) so vale em forma
  com `permite_parcelas` (derivado do tipo = so cartao de credito) e e gravado na Baixa —
  informativo, sem derivar datas/valores (ADR-0011). Rotulo via `BaixaPagamento::rotuloForma()`.
- **Estorno ao cancelar** (etapas/produto): `estornarPagamentoSeExistir` ->
  `CaixaService::estornarPagamento` (parcelas Pendente->Cancelado, Pagamento->Estornado; toda baixa
  recebe `estornado_em`; contra-lancamento por-baixa **so quando ha `Lancamento` de origem** — a
  gaveta: `Lancamento` de debito `categoria=estorno` na conta de origem. Cartao/pix nao tem
  lancamento, so a marca). Etapas: cancela tambem agendamentos `agendado|confirmado`.
  Produto: devolve estoque (`increment` + MovimentoEstoque entrada).
- **`cancelarUnico` faz duas coisas diferentes** (ADR-0018): atendimento **em aberto** -> delega a
  `CancelarAgendamentoAction` (cancela E estorna); atendimento **ja Finalizado** -> so estorna, e o
  agendamento continua Finalizado ("o servico foi prestado"). Na tela o botao vira **"Estornar
  cobranca"**. Sem essa distincao, toda cobranca feita na finalizacao ficaria sem caminho de volta.
- **Modo cobranca na tela de venda**: `GET /vendas/nova?agendamento={id}`. O bloco de
  cliente/servico/horario continua no DOM (o `venda-create.js` le `cfg.agendamento` e preenche o
  MESMO estado do fluxo normal) mas sai da tela via `hidden`. Nao ha segunda UI de recebimento —
  quem mexer no `venda-create.js` precisa saber que existem dois modos.
- `qtd_etapas` da venda = `count($data->datas)` (nao vem do servico).

## Veja tambem
- `.claude/rules/modelo-financeiro.md` — Titulo+Parcela+Baixa, `CriarPagamentoComParcelasAction`,
  `CaixaService::darBaixaParcelaPagamento` / `estornarPagamento`, `CondicaoPagamento::geraParcelas()`.
- `.claude/rules/modulos/agenda.md` (conflito/duracao) e `servico.md` (tipo unico/etapas).
- `.claude/rules/multi-tenant-seguranca.md` — `comEmpresaDeCriacao`, contexto de empresa.
