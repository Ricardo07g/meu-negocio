# Modulo: Caixa

Controle de caixa diario: abertura, movimentacoes, sangria, reforco, fechamento.

## Localizacao

`app/Modules/Caixa/`

## Camadas

| Camada | Arquivos |
|--------|----------|
| Models | Caixa.php, MovimentoCaixa.php, BaixaPagamento.php |
| Controllers | CaixaController.php |
| Services | CaixaService.php |
| DTOs | AbrirCaixaData.php, FecharCaixaData.php, MovimentoCaixaData.php |
| Requests | AbrirCaixaRequest.php, FecharCaixaRequest.php, MovimentoCaixaRequest.php |
| Policies | CaixaPolicy.php |
| Views | index, abrir (create), show |
| Migrations | create_caixas, create_baixas_pagamento, create_movimentos_caixa |

## Models

### Caixa
- Tabela: `caixas`
- Traits: PertenceARede, PertenceAEmpresa
- Casts: data → date, saldo_abertura/saldo_fechamento → decimal:2, status → StatusCaixa, fechado_em → datetime
- Metodo: `saldoCalculado()` → saldo_abertura + entradas - saidas
- Relacoes: usuario, fechadoPor, movimentos (hasMany)

### MovimentoCaixa
- Tabela: `movimentos_caixa`
- Casts: tipo → TipoMovimentoCaixa, valor → decimal:2, forma_pagamento → FormaPagamento
- Relacoes: caixa, baixaPagamento, despesa

### BaixaPagamento
- Tabela: `baixas_pagamento`
- Traits: PertenceARede, PertenceAEmpresa
- Casts: valor → decimal:2, data → datetime, forma_pagamento → FormaPagamento
- Relacoes: pagamento, caixa, movimentoCaixa (hasOne)

## Status (StatusCaixa enum)

| Valor | Descricao |
|-------|-----------|
| Aberto | Caixa em operacao |
| Fechado | Caixa encerrado |

## Tipos de movimento (TipoMovimentoCaixa enum)

| Valor | Descricao |
|-------|-----------|
| Entrada | Recebimento (pagamento de venda) |
| Saida | Saida de caixa (despesa) |
| Sangria | Retirada parcial de dinheiro |
| Reforco | Adicao de dinheiro ao caixa |

## CaixaService — regras de negocio

### caixaAberto()
Retorna caixa com status "aberto" da empresa atual (ou null).

### abrir()
- So pode ter 1 caixa aberto por vez (lanca excecao se ja existe)
- Registra data atual, usuario que abriu, saldo de abertura

### fechar()
- Registra saldo_fechamento, fechado_em, fechado_por
- Muda status para "fechado"

### registrarEntrada/Saida/Sangria/Reforco()
Cria MovimentoCaixa com tipo correspondente.

### darBaixaPagamento()
1. Valor nao pode exceder saldo restante do pagamento
2. Atualiza valor_pago no pagamento
3. Se valor_pago >= valor total → marca pagamento como "pago"
4. Cria BaixaPagamento
5. Se caixa aberto → cria entrada no caixa automaticamente
6. Tudo em transacao

## Calculo de saldo no CaixaController.show()

```
saldo_atual = saldo_abertura + total_entradas + total_reforcos - total_saidas - total_sangrias
```
