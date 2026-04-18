# Modulo: Pagamento

Gerencia pagamentos de vendas (avulso, pacote, produto). Suporta pagamento parcial via baixas.

## Localizacao

`app/Modules/Pagamento/`

## Camadas

| Camada | Arquivos |
|--------|----------|
| Models | Pagamento.php |
| Controllers | PagamentoController.php |
| Services | PagamentoService.php |
| Actions | RegistrarPagamentoAction.php |
| DTOs | RegistrarPagamentoData.php |
| Requests | RegistrarPagamentoRequest.php |
| Policies | PagamentoPolicy.php |
| Views | index, create, show |
| Migrations | create_pagamentos_table, add_venda_refs, add_valor_pago |

## Model: Pagamento

- Tabela: `pagamentos`
- Traits: PertenceARede, PertenceAEmpresa, RegistraAtividade, SoftDeletes
- Fillable: rede_id, empresa_id, cliente_id, agendamento_id, venda_pacote_id, venda_produto_id, valor, valor_pago, forma_pagamento, status, descricao
- Casts: valor/valor_pago → decimal:2, forma_pagamento → FormaPagamento, status → StatusPagamento
- Metodo: `saldoRestante()` → valor - valor_pago
- Relacoes: cliente, agendamento, vendaPacote, vendaProduto, baixas (hasMany BaixaPagamento)

## Status (StatusPagamento enum)

```
Pendente → Pago
         → Cancelado
Pago → Estornado (quando agendamento e cancelado)
```

## Formas de pagamento (FormaPagamento enum)

| Valor | Descricao |
|-------|-----------|
| Pix | Pagamento via PIX |
| Dinheiro | Dinheiro fisico |
| Cartao | Cartao (debito/credito) |
| Fiado | Anotado para pagamento futuro |

## Pagamento parcial

- `valor` = valor total da venda
- `valor_pago` = quanto ja foi pago (inicia em 0 ou valor total se pago na hora)
- Baixas de pagamento (BaixaPagamento) registram cada parcela paga
- Quando `valor_pago >= valor` → status muda para "pago"

## Vinculacao

Um pagamento pode estar vinculado a:
- `agendamento_id` — venda avulso
- `venda_pacote_id` — venda pacote
- `venda_produto_id` — venda produto

Todos nullable (pagamento pode ser avulso/manual tambem).

## Schema: pagamentos

| Coluna | Tipo | Default |
|--------|------|---------|
| id | bigint PK | auto |
| rede_id | FK redes | — |
| empresa_id | FK empresas | — |
| cliente_id | FK clientes | null |
| agendamento_id | FK agendamentos | null |
| venda_pacote_id | FK vendas_pacote | null |
| venda_produto_id | FK vendas_produto | null |
| valor | decimal(10,2) | — |
| valor_pago | decimal(10,2) | 0 |
| forma_pagamento | string(20) | — |
| status | string(20) | 'pendente' |
| descricao | text | null |
| deleted_at | timestamp | null |
