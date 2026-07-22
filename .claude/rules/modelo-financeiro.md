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
- `TipoFormaPagamento`: `dinheiro`, `pix`, `cartao_debito`, `cartao_credito`, `boleto`, `crediario` —
  tipo-base de uma `FormaPagamento` (NAO e a forma em si; a forma e uma linha de `formas_pagamento`,
  que carrega `conta_destino_id` -> conta onde o dinheiro cai). `pix` e configuravel
  (`recebivelConfiguravel()`): direto ao banco ou via maquineta. **ADR-0011:** `gera_recebivel` ainda
  roteia a conta destino, mas NAO produz mais `Recebivel` — toda forma nao-gaveta vira so uma Baixa.
- `StatusRecebivel`: `Previsto`, `Recebido`, `Cancelado` — **dormente** (ADR-0011 aposentou o
  `Recebivel`; enum/model existem mas nao sao mais escritos; removidos na Fatia 2).

## Onde a baixa cai: "fluxo, nao saldo" (ADR-0011)
A `BaixaPagamento` E o registro do recebimento por forma (o painel do dia le por ela). O motor
(`aplicarBaixaParcela`) resolve a **conta destino** e ramifica por **ela ser do tipo Caixa**:
- **Conta Caixa (gaveta / dinheiro fisico):** EXIGE caixa aberto e grava UM `Lancamento` (credito no
  recebimento / debito na despesa) com o `caixa_id` da sessao — mantem o saldo reconciliavel da gaveta.
- **Qualquer outra conta** (cartao, pix direto ou maquineta, boleto, crediario, banco): **so a Baixa**
  registra o fluxo. Sem `Lancamento`, sem `Recebivel`, sem exigir caixa. **Nao mantemos saldo de banco**
  (desatualiza fora do sistema). Regra unica de caixa: exige caixa aberto ⟺ `conta.tipo === caixa`.

> **ADR-0011 supersede a parte de recebiveis do ADR-0010:** cartao/pix-maquineta NAO geram mais
> `Recebivel` (some "a cair / disponivel / data prevista / valor liquido de taxa"); antecipacao vira so
> um marcador informativo na forma. Despesa NUNCA gerou recebivel. Ver `modulos/forma-pagamento.md`,
> `modulos/caixa.md`, `modulos/conta.md` e ADR-0011.

## Venda -> Pagamento -> Caixa
- **A vista**: `CriarPagamentoComParcelasAction` cria Pagamento + 1 parcela e baixa automaticamente
  via `CaixaService::darBaixaParcelaPagamento` (exige caixa aberto, pre-validado no controller antes
  da transacao).
- **A prazo**: cria Pagamento + N parcelas Pendente -> aparecem em Contas a Receber -> baixa por
  parcela (forma real registrada na baixa, exige caixa aberto).

## Estorno ao cancelar
`CaixaService::estornarPagamento`: parcelas Pendente->Cancelado, seta `Pagamento.status = Estornado`.
Cada `BaixaPagamento` ganha **`estornado_em`** — o marcador unico que o painel do dia neta (recebido −
estornado, pelo bruto da baixa, pela data do estorno). **So a baixa da gaveta (dinheiro)** tem
`Lancamento` a reverter: contra-lancamento de debito (`categoria = estorno`) na mesma conta/caixa (guard
de caixa fechado preservado). Cartao/pix/banco nao tem lancamento — nada a reverter, so a marca. Estoque
devolvido e agendamentos cancelados pelo `VendaService`.

## Caixa Diario
Navegacao prev/next por dia (`?data=YYYY-MM-DD`), 1 caixa por empresa/dia, permite retroativo.
Reabertura via `ReabrirCaixaData`/`ReabrirCaixaRequest`. O caixa e a **sessao da conta-caixa**
(`caixas.conta_id`); sangria/reforco criam um `Lancamento` (`categoria` `sangria`/`reforco`).

## Tabelas
pagamentos, parcelas_pagamento, baixas_pagamento · despesas, parcelas_despesa, baixas_despesa,
categorias_despesa · caixas · contas, lancamentos · recebiveis, faturas.

> Modelo financeiro detalhado tambem em ADR-0002 e o razao unificado em ADR-0010 (`docs/ADR/`).
