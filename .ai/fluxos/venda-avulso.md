# Fluxo: Venda Avulso

Venda de servico unico (uma sessao). Cria agendamento + pagamento.

## Trigger

Usuario acessa `/vendas/nova`, seleciona tipo "servico" com servico do tipo "avulso".

## Pre-requisito

- Caixa deve estar aberto (VendaController verifica)

## Passo a passo

```
1. Usuario preenche: cliente, servico, atendente, data/hora, forma pagamento, status pagamento
       ↓
2. VendaController.store() (tipo = avulso)
       ↓
3. VendaService.criarAvulso(CriarAgendamentoData, formaPagamento, statusPagamento)
       ↓
   3a. CriarAgendamentoAction.executar()
       - Calcula fim = inicio + servico.duracao
       - Verifica conflito de horario do atendente
       - Se conflito → ConflitoAgendamentoException
       - Cria Agendamento (status: agendado)
       ↓
   3b. Cria Pagamento
       - Vinculado ao agendamento_id
       - valor = servico.valor
       - forma_pagamento e status conforme informado
       ↓
   3c. Se status = "pago" e caixa aberto:
       - CaixaService.registrarEntrada() → cria MovimentoCaixa tipo entrada
       ↓
4. Redirect para vendas.index
```

## Entidades criadas

| Entidade | Quantidade |
|----------|-----------|
| Agendamento | 1 |
| Pagamento | 1 |
| MovimentoCaixa | 1 (se pago + caixa aberto) |

## Ciclo de vida posterior

- Agendamento segue ciclo: agendado → confirmado → finalizado/cancelado
- Se cancelado: CancelarAgendamentoAction estorna pagamento automaticamente
- Ver: [ciclo-agendamento.md](ciclo-agendamento.md)
