# Fluxo: Ciclo de Vida do Pagamento

## Estados (StatusPagamento)

```
┌──────────┐    baixa total     ┌──────┐    cancelar agendamento    ┌───────────┐
│ Pendente │ ────────────────→ │ Pago │ ──────────────────────────→ │ Estornado │
└──────────┘                    └──────┘                             └───────────┘
     │
     │          cancelar
     └────────────────────→ ┌───────────┐
                            │ Cancelado │
                            └───────────┘
```

## Transicoes

### Pendente → Pago
**Caminho 1 — pagamento imediato na venda:**
- VendaService cria pagamento ja com status "pago"
- valor_pago = valor total

**Caminho 2 — baixa posterior:**
- CaixaService.darBaixaPagamento()
- Cria BaixaPagamento + atualiza valor_pago
- Se valor_pago >= valor → status muda para "pago"

### Pago → Estornado
- Automatico quando agendamento vinculado e cancelado
- CancelarAgendamentoAction verifica se pagamento existe e esta "pago"
- Muda status para "estornado"

### Pendente → Cancelado
- Cenario manual (nao implementado automaticamente no fluxo atual)

## Pagamento parcial

| Campo | Descricao |
|-------|-----------|
| valor | Valor total da venda |
| valor_pago | Quanto ja foi pago (soma de baixas) |
| saldoRestante() | valor - valor_pago |

Baixas de pagamento (BaixaPagamento) registram cada parcela:
1. Valida que valor da baixa <= saldoRestante
2. Incrementa valor_pago
3. Se valor_pago >= valor → marca como "pago"
4. Se caixa aberto → cria entrada automatica

## Formas de pagamento

Pix, Dinheiro, Cartao, Fiado (FormaPagamento enum).
Definida na criacao e pode variar por baixa (pagamento misto).

## Vinculacoes

| Campo | Tipo de venda |
|-------|---------------|
| agendamento_id | Venda avulso |
| venda_pacote_id | Venda pacote |
| venda_produto_id | Venda produto |
| cliente_id | Todos (opcional) |
