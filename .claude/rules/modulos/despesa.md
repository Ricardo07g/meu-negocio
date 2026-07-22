---
paths:
  - "app/Modules/Despesa/**"
---

# Modulo: Despesa

Contas a pagar. Titulo `Despesa` + `ParcelaDespesa` (mesmo modelo Titulo+Parcela+Baixa do Pagamento,
espelhado para saida). Inclui CRUD de `CategoriaDespesa` (catalogo, rede-level). Diferente do
Pagamento, a Despesa TEM criacao manual (`create`/`store`) — nao nasce de venda.

## Entidades & status
- `Despesa` (tabela `despesas`): `nome`, `fornecedor_nome`, `documento`, `observacoes`,
  `valor_total`, `condicao_pagamento`, `forma_recebimento_prazo`, `mes_referencia`, `data_emissao`,
  `status`, FK nullable `categoria_despesa_id`. Status = enum `StatusDespesa`: `Pendente`, `Parcial`,
  `Paga`, `Cancelada` (note: NAO tem `Estornado` — diferente de StatusPagamento).
- `ParcelaDespesa` (tabela `parcelas_despesa`): mesmos campos da ParcelaPagamento (`numero`, `total`,
  `valor`, `valor_pago`, `data_vencimento`, `forma_pagamento` nullable, `status` `StatusParcela`,
  `observacao`).
- `CategoriaDespesa` (tabela `categorias_despesa`): rede-level (so `rede_id`, SEM `empresa_id` —
  catalogo compartilhado). Campos `descricao`, `ativo`. Scope `ativos()`. SoftDeletes.
- `Despesa::recalcularStatus()` deriva o status do titulo das parcelas (ignora Cancelado): todas
  pagas -> Paga; alguma paga -> Parcial; nenhuma -> Pendente; nenhuma ativa -> Cancelada.

## Camadas-chave
- `CriarDespesaComParcelasAction::executar(CriarDespesaData)` — cria titulo + parcelas (mesma logica
  do Pagamento: a vista 1 parcela, parcelado N ou `parcelas_personalizadas`).
- `DespesaService` — `listar(filtros)` (status incl. `vencidas`, q em nome/fornecedor/documento/id,
  categoria_id, situacao, mes_referencia), `criar` (delega a Action), `excluir`,
  `cancelarDespesa(Despesa, ?motivo)` (cancela parcelas Pendente+Renegociado),
  `cancelarParcela(ParcelaDespesa, ?motivo)`. NAO tem baixa (delega ao CaixaService).
- `DespesaController` — `index`, `create`, `store` (monta `CriarDespesaData` em `montarDados`),
  `cancelar`, `destroy`, `baixaParcelaForm`, `baixaParcela` (chama
  `CaixaService::darBaixaParcelaDespesa`), `cancelarParcela`, `contasAPagar`, `recibo` (PDF).
- `CategoriaDespesaController` — CRUD de categorias (resource except show).
- DTOs `CriarDespesaData`, `CategoriaDespesaData`. Requests `SalvarDespesaRequest`,
  `SalvarCategoriaDespesaRequest` (proprios) + reuso de `SalvarBaixaParcelaRequest` /
  `CancelarParcelaRequest` do **modulo Pagamento**. Policies `DespesaPolicy`,
  `CategoriaDespesaPolicy` (permissoes `despesa.ver/criar/editar/excluir`).

## Regras de negocio / gotchas
- Rotas: `despesas` (resource except show/edit/update), `categorias-despesa`,
  `parcelas-despesa/{parcela}/...` (baixa-form GET, baixa POST, cancelar PATCH), `despesas/{}/cancelar`
  PATCH, `contas-a-pagar`. Tudo sob `verificar.plano:financeiro`. NAO ha rota de editar/atualizar
  despesa.
- A baixa de parcela gera um `Lancamento` de **debito** na conta destino (vs credito no Pagamento) e
  exige caixa aberto so quando a conta destino e do tipo caixa — esta no `CaixaService`, nao aqui.
  Despesa NUNCA gera recebivel.
- `valorPago()` = soma `valor_pago` das parcelas. `totalPagoLiquido()` soma `valorTotal()` das baixas.
- `cancelarDespesa` rejeita despesa ja Paga ou ja Cancelada (`NegocioException`).
- `ParcelaDespesa::statusEfetivo()` deriva `Vencido` em leitura, igual ao Pagamento.

## Veja tambem
- `.claude/rules/modelo-financeiro.md` (Titulo+Parcela+Baixa, enums financeiros).
- `.claude/rules/multi-tenant-seguranca.md` (Despesa = empresa-level; CategoriaDespesa = rede-level).
