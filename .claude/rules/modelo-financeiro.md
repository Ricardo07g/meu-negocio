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
`forma_pagamento` mora na **parcela/baixa**, NUNCA no titulo. Fiado = `condicao_pagamento = a_prazo`.

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
- `FormaPagamento`: pix, dinheiro, cartao etc. — **na parcela/baixa, nao no titulo.**

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
