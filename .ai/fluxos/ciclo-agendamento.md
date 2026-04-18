# Fluxo: Ciclo de Vida do Agendamento

## Estados (StatusAgendamento)

```
┌──────────┐    confirmar()    ┌────────────┐    finalizar()    ┌─────────────┐
│ Agendado │ ──────────────→  │ Confirmado │ ──────────────→  │ Finalizado  │
└──────────┘                   └────────────┘                   └─────────────┘
     │                              │
     │         cancelar()           │         cancelar()
     └──────────────────→ ┌────────────┐ ←──────────────────────┘
                          │ Cancelado  │
                          └────────────┘
```

## Transicoes

### Agendado → Confirmado
- `AgendamentoService.confirmar()`
- Atualiza status para "confirmado"
- Sem efeitos colaterais

### Agendado/Confirmado → Finalizado
- `FinalizarAgendamentoAction.executar()`
- Somente aceita status "agendado" ou "confirmado"
- Atualiza status para "finalizado"
- Sem efeitos colaterais diretos

### Agendado/Confirmado → Cancelado
- `CancelarAgendamentoAction.executar()`
- Nao pode cancelar se ja "finalizado" (ValidationException)
- Atualiza status para "cancelado"
- **Efeito colateral**: se pagamento existe e esta "pago", muda para "estornado"

### Finalizado → (nenhuma transicao)
Estado terminal. Nao pode ser cancelado nem revertido.

## Validacoes na criacao

- `CriarAgendamentoAction` verifica conflito de horario do atendente
- Conflito: outro agendamento (nao cancelado) do mesmo atendente que sobreponha horario
- Formula: `existente.inicio < novo.fim AND existente.fim > novo.inicio`
- `fim` auto-calculado: `inicio + servico.duracao` (se nao informado)

## Onde muda status

| Acao | Responsavel | Arquivo |
|------|-------------|---------|
| Criar | CriarAgendamentoAction | Actions/CriarAgendamentoAction.php |
| Confirmar | AgendamentoService | Services/AgendamentoService.php |
| Finalizar | FinalizarAgendamentoAction | Actions/FinalizarAgendamentoAction.php |
| Cancelar | CancelarAgendamentoAction | Actions/CancelarAgendamentoAction.php |
