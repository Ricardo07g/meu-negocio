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

## Camadas-chave
- **`VendaController`**: `store` -> `processarVenda` roteia por `tipo_venda`/`servico->isEtapas()`.
  Escrita envolta em `comEmpresaDeCriacao($empresaId, fn ...)` (trait `DefineEmpresaDeCriacao`).
  Edit/update e cancelar por tipo: `*Unico` / `*Etapas` / `*Produto`; `recibo($tipo,$id)` gera PDF.
- **`VendaService`**: `criarUnico`, `criarEtapas`, `criarVendaProduto` (cada um em `DB::transaction`);
  `cancelar*` (estorno); `atualizar*` (bloqueia se ja ha parcela paga / status nao editavel);
  `listar` (merge dos 3 tipos + paginacao manual com filtros pesados). `podeEditar(?Pagamento)` =
  `valorPago() <= 0`.
- **Actions**: `VenderEtapasAction` (cria VendaEtapas + N Agendamentos, **detecta conflito por
  sessao** e acumula em `ConflitoAgendamentoException` com lista de datas), `CriarVendaProdutoAction`
  (cria venda + itens, baixa estoque, cria Pagamento), `SincronizarItensVendaProdutoAction` (diff de
  itens na edicao: ajusta estoque pela diferenca, devolve removidos).
- **DTOs/Requests**: `VenderEtapasData` (cliente/servico/atendente, `valor_total`, `horario`,
  `datas[]`, `horarios[]?`). `CriarVendaRequest` (unico, ramifica por `tipo_venda` produto/servico e
  por `isEtapas`). Atualizacao: `AtualizarVenda{Etapas,Produto,Unico}Request`.
- **`VendaEtapasPolicy`**: usa permissoes do **agendamento** (`agendamento.ver|criar|cancelar`), nao
  `venda.*`. Metodos `viewAny/view/create/cancel`.

## Regras de negocio / gotchas
- A vista pode exigir caixa aberto: `processarVenda` checa `caixaService->exigeCaixaAberto($forma)`
  ANTES da transacao (so quando a forma e imediata e a conta destino e do tipo caixa — dinheiro sim;
  pix-direto/cartao nao). A baixa automatica e `VendaService::baixarAVistaSeAplicavel` ->
  `CaixaService::darBaixaParcelaPagamento` (so quando `AVista` E forma informada).
- **Estorno ao cancelar** (etapas/produto): `estornarPagamentoSeExistir` ->
  `CaixaService::estornarPagamento` (parcelas Pendente->Cancelado, Pagamento->Estornado;
  contra-lancamento por-baixa: cancela `Recebivel` se houver, senao `Lancamento` de debito
  `categoria=estorno` na conta de origem). Etapas: cancela tambem agendamentos `agendado|confirmado`.
  Produto: devolve estoque (`increment` + MovimentoEstoque entrada). `cancelarUnico` cancela o
  Agendamento (Action) + estorna. (Contraste: cancelar pela tela de Agenda NAO estorna no caixa.)
- Edicao bloqueada se `podeEditar` falso (parcela ja paga) ou status fora de Ativo/agendado etc.
- `qtd_etapas` da venda = `count($data->datas)` (nao vem do servico).

## Veja tambem
- `.claude/rules/modelo-financeiro.md` — Titulo+Parcela+Baixa, `CriarPagamentoComParcelasAction`,
  `CaixaService::darBaixaParcelaPagamento` / `estornarPagamento`, `CondicaoPagamento::geraParcelas()`.
- `.claude/rules/modulos/agenda.md` (conflito/duracao) e `servico.md` (tipo unico/etapas).
- `.claude/rules/multi-tenant-seguranca.md` — `comEmpresaDeCriacao`, contexto de empresa.
