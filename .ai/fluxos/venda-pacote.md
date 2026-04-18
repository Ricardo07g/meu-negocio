# Fluxo: Venda Pacote

Venda de servico com multiplas sessoes. Cria VendaPacote + N agendamentos + pagamento.

## Trigger

Usuario acessa `/vendas/nova`, seleciona servico do tipo "pacote".

## Pre-requisito

- Caixa deve estar aberto
- Servico deve ter tipo = Pacote

## Passo a passo

```
1. Usuario preenche: cliente, servico(pacote), atendente, lista de datas/horarios, forma pagamento
       ↓
2. VendaController.store() (tipo = pacote)
       ↓
3. VendaService.criarPacote(VenderPacoteData, formaPagamento, statusPagamento)
       ↓
   3a. VenderPacoteAction.executar()
       - Para cada data/horario:
         - Calcula fim = inicio + servico.duracao
         - Verifica conflito do atendente
       - Se QUALQUER sessao tem conflito → ConflitoAgendamentoException (lista datas)
       - Cria VendaPacote (status: ativo, qtd_sessoes = count datas)
       - Cria N Agendamentos (todos vinculados ao pacote)
       ↓
   3b. Cria Pagamento
       - Vinculado ao venda_pacote_id
       - valor = valor_total do pacote
       ↓
   3c. Se pago + caixa aberto → registra entrada no caixa
       ↓
4. Redirect para vendas.index
```

## Entidades criadas

| Entidade | Quantidade |
|----------|-----------|
| VendaPacote | 1 |
| Agendamento | N (1 por sessao) |
| Pagamento | 1 |
| MovimentoCaixa | 1 (se pago + caixa aberto) |

## Controle de sessoes

- `VendaPacote.sessoesRealizadas()` → count de agendamentos finalizados
- `VendaPacote.sessoesPendentes()` → count de agendamentos agendados/confirmados
- Status do pacote: ativo → concluido (todas sessoes finalizadas) / cancelado

## Cancelamento

`VendaService.cancelarPacote()`:
- Cancela todos agendamentos com status agendado/confirmado
- Pagamento NAO e estornado automaticamente (diferente do avulso)
