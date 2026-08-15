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
resolve `CondicaoPagamento`/`FormaPagamento`/parcelas e **pre-valida caixa aberto SE a vista E a forma
exige caixa** (`CaixaService::exigeCaixaAberto` = forma imediata cuja conta destino e do tipo caixa —
i.e. dinheiro; pix-direto e cartao NAO exigem caixa; caixa fechado -> erro, sem persistir). Cada tipo
cria o titulo `Pagamento` via `CriarPagamentoComParcelasAction` e, se a vista, baixa a parcela unica em
seguida (`VendaService::baixarAVistaSeAplicavel` -> `CaixaService::darBaixaParcelaPagamento`).

- **Servico unico** (servico NAO `isEtapas()`): `criarUnico` -> `CriarAgendamentoAction` (calcula
  `fim` por `servico.duracao`, detecta conflito, cria `Agendado`) + `Pagamento` (FK `agendamento_id`,
  valor = `servico.valor`). E a porta do **pre-pago**: vende antes, atende depois. Quem atende
  primeiro e cobra no fim usa `cobrarAtendimento` (ver "Ciclo do agendamento").
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
- parcelas `Pendente` -> `Cancelado`; titulo -> `StatusPagamento::Estornado`. Toda baixa recebe
  **`estornado_em`** (marcador unico que o painel do dia neta). Contra-lancamento **por-baixa,
  discriminado pela existencia de um `Lancamento` de origem** (ADR-0011 — NAO por `Recebivel`, que
  nao e mais gerado): baixa da gaveta (dinheiro, tem `Lancamento`) gera um `Lancamento` de debito
  (`categoria = estorno`) na conta de ORIGEM (bloqueia se essa conta for caixa e o caixa da data
  estiver fechado -> `NegocioException`, reabra antes); cartao/pix/boleto nao tem lancamento — nada a
  reverter, so a marca.
- Etapas: agendamentos `agendado|confirmado` -> `cancelado`; venda -> `Cancelado`.
- Produto: devolve estoque (`increment` + `MovimentoEstoque` tipo `entrada`) -> venda `Cancelada`.
- Edicao de venda so e permitida enquanto `podeEditar()` (nenhuma parcela paga: `valorPago()<=0`).

## Ciclo do agendamento (ADR-0018)
`Agendado -> Confirmado -> Finalizado`; `Agendado|Confirmado -> Cancelado` (terminal: Finalizado).
`AgendaController` confirmar/finalizar/cancelar (aceitam JSON via `expectsJson()`).

**Finalizar exige desfecho declarado.** Sem titulo (`pagamentos.agendamento_id`) e sem
`motivo_sem_cobranca`, `FinalizarAgendamentoAction` lanca `NegocioException` — o atendimento nao
encerra "mudo". As duas saidas:
- **Finalizar e cobrar** -> `GET /vendas/nova?agendamento={id}` (modo cobranca, reusa o bloco de
  recebimento da venda) -> `POST /vendas` com `agendamento_id` -> `VendaService::cobrarAtendimento`
  cria o titulo (valor = `servico.valor`) + baixa se a vista + **finaliza**, tudo em uma transacao.
  Recusa se ja houver titulo.
- **Finalizar sem cobrar** -> `PATCH agenda/{id}/finalizar` com `motivo_sem_cobranca`
  (cortesia/retorno/garantia/interno).

**Cancelar x estornar.** Em aberto: `CancelarAgendamentoAction` cancela e **estorna de verdade** via
`CaixaService::estornarPagamento` (contra-lancamento por baixa, `estornado_em`, recusa em caixa
fechado). Ja finalizado: a agenda recusa; a reversao vem de `vendas.cancelar-unico`, que so estorna
e mantem o agendamento Finalizado (situacao "Estornado"). Estorno **nao e idempotente** — ha guarda
contra titulo ja desfeito nos dois caminhos.

`reagendar` (PATCH AJAX) move `inicio`/`fim` sem revalidar conflito (pendente).

## Ciclo do pagamento a prazo (contas a receber)
A prazo: titulo nasce `Pendente` com N parcelas `Pendente` (sem baixa). Aparecem em Contas a Receber
(`PagamentoController::contasAReceber`). Baixa por parcela em `parcelas-pagamento/{parcela}/baixa`
-> `CaixaService::darBaixaParcelaPagamento`: cria `BaixaPagamento` (com `forma_pagamento_nome` e, em
cartao parcelavel, `parcelas_cartao` — informativo, rende o rotulo "Cartao de Credito 2x" via
`BaixaPagamento::rotuloForma()`) e, **so se a conta destino e do tipo caixa**, um `Lancamento` de
credito (aí EXIGE caixa aberto). Cartao/pix/boleto/crediario: so a Baixa, sem `Recebivel` (ADR-0011).
Soma `valor_pago`, marca parcela `Pago` se
quitada, e `Pagamento::recalcularStatus()`. Defesa em profundidade: controller seta
`session('empresa_criacao_atual', $parcela->empresa_id)` no try e `forget()` no finally.

## Ciclo do caixa (diario: abrir -> movimentar -> fechar)
`CaixaController::index?data=YYYY-MM-DD` navega por dia; **1 caixa por empresa/dia**, permite
retroativo. O caixa e a **sessao da conta-caixa** (`caixas.conta_id` -> conta `eh_caixa_padrao`).
Abertura `CaixaService::abrir` recusa se ja existe caixa na data. Os movimentos sao `Lancamento`
(ligados por `caixa_id`): credito/debito automaticos por baixa de Pagamento/Despesa e estorno,
`sangria`/`reforco` (`categoria`) manuais. Saldo do dia = `saldo_abertura + Σ lancamentos da sessao`
(`Caixa::saldoCalculado()`; reforco = credito, sangria = debito); `saldo_abertura`/`saldo_fechamento`
sao contagem fisica (nao viram lancamento). `fechar` grava saldo/fechado_em/fechado_por -> `Fechado`.
`reabrir` (so de Fechado) limpa fechamento, volta a `Aberto` e loga atividade. Caixa Diario opera 1
empresa: sem contexto e com varias acessiveis, escolhe a primeira.

## Movimentacao de estoque
Manual: `EstoqueService::registrarMovimento` (em transacao) cria `MovimentoEstoque` e aplica:
`entrada` => `increment`, `saida` => `decrement`, `ajuste` => `update(['quantidade'=>n])` (valor
ABSOLUTO, nao soma). Automatico: venda de produto gera `saida`; cancelar a venda gera `entrada`.
Sem SoftDeletes (append-only). Sem trava de quantidade negativa. Rotas exigem
`verificar.plano:estoque`.

## Despesa (contas a pagar) — espelho do Pagamento
Mesmo Titulo+Parcela+Baixa, lado pagar. Baixa via `CaixaService::darBaixaParcelaDespesa` -> `BaixaDespesa`
+ `Lancamento` de debito na conta destino (EXIGE caixa aberto so quando a conta destino e do tipo
caixa). Despesa NUNCA gera recebivel (`gera_recebivel` forcado a `false`). Ver
`.claude/rules/modulos/despesa.md`.
