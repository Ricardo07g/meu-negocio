---
paths:
  - "app/Modules/Pagamento/**"
---

# Modulo: Pagamento

Contas a receber. Gerencia o titulo `Pagamento` e suas `ParcelaPagamento` (baixa parcial,
renegociacao, cancelamento). A baixa efetiva e o recibo PDF. NAO faz CRUD manual de titulos — eles
nascem da Venda (ver `CriarPagamentoComParcelasAction`).

## Entidades & status
- `Pagamento` (tabela `pagamentos`) = titulo a receber. Campos reais: `valor_total`, `desconto`,
  `acrescimo`, `condicao_pagamento`, `forma_recebimento_prazo`, `mes_referencia`, `status`,
  `descricao` + FKs nullable `cliente_id`, `agendamento_id`, `venda_etapas_id`, `venda_produto_id`.
  Status agregado = enum `StatusPagamento`: `Pendente`, `Parcial`, `Pago`, `Cancelado`, `Estornado`.
- `ParcelaPagamento` (tabela `parcelas_pagamento`): `numero`, `total`, `valor`, `valor_pago`,
  `data_vencimento`, `mes_referencia`, `forma_pagamento` (nullable, preenchida na baixa), `status`
  (enum `StatusParcela`), `observacao`.
- `Pagamento::recalcularStatus()` deriva o status do titulo das parcelas (ignora Cancelado): todas
  pagas -> Pago; alguma paga -> Parcial; nenhuma -> Pendente; nenhuma ativa -> Cancelado. Chame-o
  apos QUALQUER mudanca de parcela.

## Camadas-chave
- `CriarPagamentoComParcelasAction::executar(CriarPagamentoData)` — cria o titulo + parcelas (a vista
  = 1 parcela venc. hoje; parcelado = N via `CalculadoraParcelas`, ou usa
  `parcelas_personalizadas` literalmente). Valida forma/numero. A baixa a vista e do caller (Venda).
- `PagamentoService` — `listar(filtros)` (status, q, origem unico/etapas/produto, situacao
  em_dia/vencido, mes_referencia), `renegociarParcela(ParcelaPagamento, RenegociarParcelaData)`,
  `cancelarParcela(ParcelaPagamento, ?motivo)`. NAO tem metodo de baixa (delega ao CaixaService).
- `PagamentoController` — `index`, `baixaParcelaForm`, `baixaParcela` (chama
  `CaixaService::darBaixaParcelaPagamento`), `renegociarParcela`, `cancelarParcela`,
  `contasAReceber` (redirect index?status=pendente), `recibo` (PDF DomPDF).
- DTOs: `CriarPagamentoData`, `RenegociarParcelaData`. Policy: `PagamentoPolicy` (permissoes
  `pagamento.ver/criar/editar/excluir`).
- Requests `SalvarBaixaParcelaRequest`, `RenegociarParcelaRequest`, `CancelarParcelaRequest` sao
  **compartilhados com Despesa**: `authorize()` escolhe a permissao via `routeIs('parcelas-despesa.*')`
  -> `despesa.editar`, senao `pagamento.editar`.

## Regras de negocio / gotchas
- Rotas usam `parcelas-pagamento/{parcela}/...` (baixa-form, baixa POST, renegociar PATCH, cancelar
  PATCH); todo o grupo financeiro exige `verificar.plano:financeiro`.
- A baixa nao mora aqui: `darBaixaParcelaPagamento` esta no `CaixaService` e EXIGE caixa aberto.
- `valorPago()` = soma de `valor_pago` das parcelas (principal). `totalRecebidoLiquido()` soma
  `valorTotal()` das baixas (principal + multa + juros − desconto). NAO confundir.
- `saldoRestante()` ignora parcelas Cancelado/Renegociado. Renegociar parcela ja paga ou cancelada
  lanca `NegocioException`; novo valor nao pode ser < `valor_pago`.
- `ParcelaPagamento::statusEfetivo()` deriva `Vencido` em tempo de leitura (pendente + vencimento no
  passado) — nao ha job batch; o status persistido continua `Pendente`.
- `forma_pagamento` so existe na parcela/baixa; o titulo guarda `condicao_pagamento` +
  `forma_recebimento_prazo`.

## Veja tambem
- `.claude/rules/modelo-financeiro.md` (Titulo+Parcela+Baixa, fluxo Venda->Pagamento->Caixa, estorno).
- `.claude/rules/multi-tenant-seguranca.md` (escopo empresa, `comEmpresaDeCriacao` na baixa).
