---
paths:
  - "app/Modules/Venda/**"
  - "app/Modules/Pagamento/**"
  - "app/Modules/Despesa/**"
  - "app/Modules/Agenda/**"
  - "app/Modules/Caixa/**"
  - "app/Modules/Estoque/**"
---

# Fluxos ponta-a-ponta

Sequencias entre modulos. Para o modelo Titulo+Parcela+Baixa e enums, ver
`.claude/rules/modelo-financeiro.md` (nao duplicar aqui).

## Onboarding (registro -> rede -> empresa -> 1o usuario)
`RegistrarController::register` -> `RedeService::criar(CriarRedeData, UsuarioData)` em
`DB::transaction`:
1. busca `Plano` `free` por nome; cria `Rede` (status `Ativa`, plano free se nao informado);
2. `CriarEmpresaAction` -> 1 `Empresa` padrao (nome = nome da rede; valida limite `empresa`);
3. `CriarUsuarioAction` -> 1 `Usuario` (papel `Admin`, `ativo=true`, `atende=true` p/ Admin),
   `assignRole('Admin')`, e sync no pivot `empresa_usuario` se `empresas` veio no DTO;
4. seeds: 6 categorias de produto, 6 produtos, 6 servicos (5 `unico` + 1 `etapas` 10 sessoes),
   5 clientes.
Depois: `Auth::login($usuario)` -> redirect `dashboard`. (Usuario seedado NAO recebe pivot — Admin
ve tudo via Role.)

## Venda (entrada unica: `POST vendas`)
`VendaController::store` envolve tudo em `comEmpresaDeCriacao($empresaId, ...)`; `processarVenda`
resolve `CondicaoPagamento`/`FormaPagamento`/parcelas e **pre-valida caixa aberto SE a vista**
(`condicao = a_vista` e caixa fechado -> erro, sem persistir). Cada tipo cria o titulo `Pagamento`
via `CriarPagamentoComParcelasAction` e, se a vista, baixa a parcela unica em seguida
(`VendaService::baixarAVistaSeAplicavel` -> `CaixaService::darBaixaParcelaPagamento`).

- **Servico unico** (servico NAO `isEtapas()`): `criarUnico` -> `CriarAgendamentoAction` (calcula
  `fim` por `servico.duracao`, detecta conflito, cria `Agendado`) + `Pagamento` (FK `agendamento_id`,
  valor = `servico.valor`).
- **Servico em etapas** (`servico->isEtapas()`): `criarEtapas` -> `VenderEtapasAction` cria 1
  `VendaEtapas` (`StatusVendaEtapas::Ativo`, `qtd_etapas = count(datas)`) + N `Agendamento` (1 por
  data; se QUALQUER data conflita -> `ConflitoAgendamentoException` listando as datas, rollback) +
  `Pagamento` (FK `venda_etapas_id`, valor = `valor_total`).
- **Produto**: `criarVendaProduto` -> `CriarVendaProdutoAction` cria `VendaProduto`
  (`StatusVendaProduto::Ativa`) + N `VendaProdutoItem` (carrinho), e por item:
  `Produto::decrement('quantidade')` + `MovimentoEstoque` tipo `saida`. Depois `Pagamento`
  (FK `venda_produto_id`). Cliente opcional (venda balcao). Sem trava de estoque negativo.

## Cancelamento de venda (estorno)
`VendaService::cancelar{Unico|Etapas|VendaProduto}` em transacao chama
`CaixaService::estornarPagamento(Pagamento)`:
- parcelas `Pendente` -> `Cancelado`; se ja houve recebimento (`valorPago()>0`) cria
  `MovimentoCaixa` tipo `saida` no caixa aberto (EXIGE caixa aberto, senao `NegocioException`);
  titulo -> `StatusPagamento::Estornado`.
- Etapas: agendamentos `agendado|confirmado` -> `cancelado`; venda -> `Cancelado`.
- Produto: devolve estoque (`increment` + `MovimentoEstoque` tipo `entrada`) -> venda `Cancelada`.
- Edicao de venda so e permitida enquanto `podeEditar()` (nenhuma parcela paga: `valorPago()<=0`).

## Ciclo do agendamento
`Agendado -> Confirmado -> Finalizado`; `Agendado|Confirmado -> Cancelado` (terminal: Finalizado).
`AgendaController` confirmar/finalizar/cancelar (aceitam AJAX). `FinalizarAgendamentoAction` so de
Agendado/Confirmado. `CancelarAgendamentoAction` bloqueia se Finalizado e, se houver `pagamento`
HasOne `Pago`, marca-o `Estornado` direto (NAO usa `estornarPagamento` — esse e do fluxo Venda; nao
mexe no caixa). `reagendar` (PATCH AJAX) move `inicio`/`fim` sem revalidar conflito.

## Ciclo do pagamento a prazo (contas a receber)
A prazo: titulo nasce `Pendente` com N parcelas `Pendente` (sem baixa). Aparecem em Contas a Receber
(`PagamentoController::contasAReceber`). Baixa por parcela em `parcelas-pagamento/{parcela}/baixa`
-> `CaixaService::darBaixaParcelaPagamento` (EXIGE caixa aberto): cria `BaixaPagamento` +
`MovimentoCaixa` tipo `entrada`, soma `valor_pago`, marca parcela `Pago` se quitada, e
`Pagamento::recalcularStatus()`. Defesa em profundidade: controller seta
`session('empresa_criacao_atual', $parcela->empresa_id)` no try e `forget()` no finally.

## Ciclo do caixa (diario: abrir -> movimentar -> fechar)
`CaixaController::index?data=YYYY-MM-DD` navega por dia; **1 caixa por empresa/dia**, permite
retroativo. Abertura `CaixaService::abrir` recusa se ja existe caixa na data. Movimentos via
`MovimentoCaixa`: `entrada`/`saida` (automaticos por baixa de Pagamento/Despesa e estorno),
`sangria`/`reforco` (manuais). Saldo do dia (calculado no controller) =
`saldo_abertura + entradas + reforcos - saidas - sangrias`. `fechar` grava saldo/fechado_em/
fechado_por -> `Fechado`. `reabrir` (so de Fechado) limpa fechamento, volta a `Aberto` e loga
atividade. Caixa Diario opera 1 empresa: sem contexto e com varias acessiveis, escolhe a primeira.

## Movimentacao de estoque
Manual: `EstoqueService::registrarMovimento` (em transacao) cria `MovimentoEstoque` e aplica:
`entrada` => `increment`, `saida` => `decrement`, `ajuste` => `update(['quantidade'=>n])` (valor
ABSOLUTO, nao soma). Automatico: venda de produto gera `saida`; cancelar a venda gera `entrada`.
Sem SoftDeletes (append-only). Sem trava de quantidade negativa. Rotas exigem
`verificar.plano:estoque`.

## Despesa (contas a pagar) — espelho do Pagamento
Mesmo Titulo+Parcela+Baixa, lado pagar. Baixa via `CaixaService::darBaixaParcelaDespesa` (EXIGE
caixa aberto) -> `BaixaDespesa` + `MovimentoCaixa` tipo `saida`. Ver `.claude/rules/modulos/despesa.md`.
