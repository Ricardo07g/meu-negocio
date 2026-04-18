# Fluxo: Venda Produto

Venda de produto fisico. Cria VendaProduto + baixa estoque + pagamento.

## Trigger

Usuario acessa `/vendas/nova`, seleciona tipo "produto".

## Pre-requisito

- Caixa deve estar aberto
- Produto deve existir e ter quantidade suficiente

## Passo a passo

```
1. Usuario preenche: cliente (opcional), produto, quantidade, valor_total, forma pagamento
       ↓
2. VendaController.store() (tipo = produto)
       ↓
3. VendaService.criarVendaProduto(cliente_id, produto_id, quantidade, valor_total, formaPagamento, statusPagamento)
       ↓
   3a. Cria VendaProduto
       ↓
   3b. Decrementa produto.quantidade
       ↓
   3c. Cria MovimentoEstoque (tipo: saida, quantidade vendida)
       ↓
   3d. Cria Pagamento
       - Vinculado ao venda_produto_id
       ↓
   3e. Se pago + caixa aberto → registra entrada no caixa
       ↓
4. Redirect para vendas.index
```

## Entidades criadas

| Entidade | Quantidade |
|----------|-----------|
| VendaProduto | 1 |
| MovimentoEstoque | 1 (tipo saida) |
| Pagamento | 1 |
| MovimentoCaixa | 1 (se pago + caixa aberto) |

## Efeitos colaterais

- `Produto.quantidade` e decrementada automaticamente
- Nao ha validacao de estoque negativo no fluxo atual (possivel melhoria)

## Nota

- Cliente e opcional (venda balcao pode nao ter cliente)
- Valor total e informado (pode ser diferente de produto.valor_venda * quantidade para descontos)
