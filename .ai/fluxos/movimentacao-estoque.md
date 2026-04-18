# Fluxo: Movimentacao de Estoque

## Tipos de movimento

### Entrada
- Registrada manualmente pelo usuario
- Incrementa `produto.quantidade`
- Uso: recebimento de mercadoria, devolucao

### Saida
- Manual ou automatica (venda de produto)
- Decrementa `produto.quantidade`
- Uso: perda, quebra, venda

### Ajuste
- Registrada manualmente pelo usuario
- Define `produto.quantidade` para valor exato (nao soma/subtrai)
- Uso: inventario, correcao de estoque

## Fluxo manual

```
1. Usuario acessa /movimentos-estoque/novo
       ↓
2. Seleciona: produto, tipo (entrada/saida/ajuste), quantidade
       ↓
3. EstoqueService.registrarMovimento()
   - Cria MovimentoEstoque
   - Atualiza produto.quantidade conforme tipo
   - Transacao
       ↓
4. Redirect para listagem
```

## Fluxo automatico (venda de produto)

```
1. VendaService.criarVendaProduto()
       ↓
2. Cria VendaProduto
       ↓
3. Decrementa produto.quantidade diretamente
       ↓
4. Cria MovimentoEstoque tipo Saida
```

Nota: a baixa automatica na venda e feita pelo VendaService, nao pelo EstoqueService.

## Requisitos

- Acesso requer plano com `tem_estoque = true`
- Middleware `verificar.plano:estoque` protege as rotas
- Cadastro de produtos e livre (nao depende de plano)

## Pontos de atencao

- Nao ha validacao de estoque negativo (quantidade pode ficar < 0)
- Movimentos nao tem soft delete (sao permanentes)
- `produto.estoque_minimo` existe no schema mas alerta nao esta implementado
