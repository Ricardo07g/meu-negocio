---
paths:
  - "app/Modules/Pagamento/**"
  - "app/Modules/Despesa/**"
  - "app/Modules/Venda/**"
  - "app/Modules/Caixa/**"
  - "app/Support/Parcelamento/**"
---

# Modelo financeiro: Titulo + Parcela + Baixa

Carrega ao mexer em Pagamento, Despesa, Venda, Caixa ou no parcelamento. Regra de ouro:
a **forma** mora na parcela/baixa (via `forma_pagamento_id` FK + snapshot `forma_pagamento_nome`),
NUNCA no titulo. Fiado = `condicao_pagamento = a_prazo`. A forma e um CATALOGO por rede
(`formas_pagamento`), nao mais um enum fixo — ver `.claude/rules/modulos/forma-pagamento.md` e ADR-0009.

## Tres entidades
- **Titulo** = `Pagamento` (a receber) ou `Despesa` (a pagar). Tem `condicao_pagamento`,
  `forma_recebimento_prazo`, valor bruto/liquido e referencia ao originador (venda, despesa avulsa).
- **Parcela** = `ParcelaPagamento` / `ParcelaDespesa`: `numero`, `data_vencimento`, `valor`,
  `valor_pago`, `status` (`StatusParcela`), `forma_pagamento` (preenchida na baixa).
- **Baixa** = `BaixaPagamento` / `BaixaDespesa`: vincula parcela + caixa + valor + multa/juros/
  desconto. Uma parcela pode ter N baixas (pagamento parcial).
- Geracao de parcelas: `App\Support\Parcelamento\CalculadoraParcelas`.

## Enums
- `CondicaoPagamento`: `a_vista`, `a_prazo`, `boleto`, `pix_parcelado`.
- `FormaRecebimentoPrazo`: canais esperados de recebimento do titulo a prazo.
- `StatusParcela`: `Pendente`, `Pago`, `Vencido`, `Cancelado`, `Renegociado`.
- `TipoFormaPagamento`: `dinheiro`, `pix`, `cartao_debito`, `cartao_credito`, `boleto` — tipo-base de
  uma `FormaPagamento` do catalogo (NAO e a forma em si; a forma e uma linha de `formas_pagamento`).
- `StatusRecebivel`: `Previsto`, `Recebido`, `Cancelado` (derivado por data).

## Recebiveis de cartao (a receber do banco)
Forma com `gera_recebivel = true` (cartao): a baixa quita o cliente e cria a `BaixaPagamento`, mas
**nao exige caixa aberto e nao gera `MovimentoCaixa`** — gera N `Recebivel` (um por parcela do cartao),
`valor_liquido = bruto × (1 − taxa/100)`, `data_prevista = venda + dias_liquidacao + 30×(i−1)`. Status
computado por data (sem job). Estorno cancela os recebiveis (por-baixa; baixa de cartao tem `caixa_id`
NULL). Dinheiro/Pix (`gera_recebivel = false`) seguem o fluxo antigo: caixa + `MovimentoCaixa`. Despesa
NUNCA gera recebivel. Ver `.claude/rules/modulos/forma-pagamento.md`, `modulos/caixa.md` e ADR-0009.

## Venda -> Pagamento -> Caixa
- **A vista**: `CriarPagamentoComParcelasAction` cria Pagamento + 1 parcela e baixa automaticamente
  via `CaixaService::darBaixaParcelaPagamento` (exige caixa aberto, pre-validado no controller antes
  da transacao).
- **A prazo**: cria Pagamento + N parcelas Pendente -> aparecem em Contas a Receber -> baixa por
  parcela (forma real registrada na baixa, exige caixa aberto).

## Estorno ao cancelar
`CaixaService::estornarPagamento`: parcelas Pendente->Cancelado, cria `MovimentoCaixa(saida)` com
`valorPago()`, seta `Pagamento.status = Estornado`. Estoque devolvido e agendamentos cancelados pelo
`VendaService`.

## Caixa Diario
Navegacao prev/next por dia (`?data=YYYY-MM-DD`), 1 caixa por empresa/dia, permite retroativo.
Reabertura via `ReabrirCaixaData`/`ReabrirCaixaRequest`. Sangria/reforco via `MovimentoCaixa`.

## Tabelas
pagamentos, parcelas_pagamento, baixas_pagamento · despesas, parcelas_despesa, baixas_despesa,
categorias_despesa · caixas, movimentos_caixa · faturas.

> Modelo financeiro detalhado tambem em ADR-0002 (`docs/ADR/`).
