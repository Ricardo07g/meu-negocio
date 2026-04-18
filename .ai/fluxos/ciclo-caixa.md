# Fluxo: Ciclo de Vida do Caixa

## Estados (StatusCaixa)

```
┌────────┐    fechar()    ┌─────────┐
│ Aberto │ ──────────→  │ Fechado │
└────────┘                └─────────┘
     │
     ├── registrarEntrada()   → MovimentoCaixa tipo Entrada
     ├── registrarSaida()     → MovimentoCaixa tipo Saida
     ├── registrarSangria()   → MovimentoCaixa tipo Sangria
     └── registrarReforco()   → MovimentoCaixa tipo Reforco
```

## Abertura

```
1. Usuario acessa /caixas/abrir
       ↓
2. Informa saldo de abertura + observacao (opcional)
       ↓
3. CaixaService.abrir()
   - Verifica se ja existe caixa aberto (so 1 por vez)
   - Se ja tem → lanca excecao
   - Cria Caixa (status: aberto, data: hoje, usuario: logado)
```

## Operacoes durante o dia

### Entrada automatica (via venda)
- VendaService cria venda com pagamento "pago"
- Se caixa aberto → `CaixaService.registrarEntrada()`
- MovimentoCaixa tipo Entrada, com forma_pagamento e descricao

### Entrada via baixa de pagamento
- CaixaService.darBaixaPagamento()
- Cria BaixaPagamento + MovimentoCaixa tipo Entrada
- Se pagamento fica totalmente pago → status "pago"

### Saida (despesa)
- `CaixaService.registrarSaida(valor, descricao, despesa_id?)`
- MovimentoCaixa tipo Saida, pode vincular a despesa

### Sangria (retirada de dinheiro)
- `CaixaService.registrarSangria(valor, descricao)`
- MovimentoCaixa tipo Sangria

### Reforco (adicao de dinheiro)
- `CaixaService.registrarReforco(valor, descricao)`
- MovimentoCaixa tipo Reforco

## Fechamento

```
1. Usuario acessa /caixas/{id} → botao Fechar
       ↓
2. Informa saldo de fechamento + observacao
       ↓
3. CaixaService.fechar()
   - Registra saldo_fechamento, fechado_em, fechado_por
   - Status → "fechado"
```

## Calculo de saldo

```
saldo_atual = saldo_abertura
            + sum(movimentos tipo Entrada)
            + sum(movimentos tipo Reforco)
            - sum(movimentos tipo Saida)
            - sum(movimentos tipo Sangria)
```

Implementado no CaixaController.show() para exibicao.

## Regra critica

**Apenas 1 caixa aberto por vez por empresa.** Tentativa de abrir segundo lanca excecao.
