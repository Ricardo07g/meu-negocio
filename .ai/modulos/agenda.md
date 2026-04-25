# Modulo: Agenda

Gerencia agendamentos de servicos. Integrado com Toast UI Calendar no frontend.

## Localizacao

`app/Modules/Agenda/`

## Camadas

| Camada | Arquivos |
|--------|----------|
| Models | Agendamento.php |
| Controllers | AgendaController.php |
| Services | AgendamentoService.php |
| Actions | CriarAgendamentoAction.php, CancelarAgendamentoAction.php, FinalizarAgendamentoAction.php |
| DTOs | CriarAgendamentoData.php, AtualizarAgendamentoData.php |
| Requests | AtualizarAgendamentoRequest.php |
| Policies | AgendamentoPolicy.php |
| Views | index (calendario), edit, show |
| Migrations | create_agendamentos_table, add_venda_pacote_id, add_observacoes |

## Model: Agendamento

- Tabela: `agendamentos`
- Traits: PertenceARede, PertenceAEmpresa, RegistraAtividade, SoftDeletes
- Fillable: rede_id, empresa_id, cliente_id, servico_id, atendente_id, venda_pacote_id, inicio, fim, status, observacoes
- Casts: inicio/fim → datetime, status → StatusAgendamento
- Relacoes: cliente, servico, atendente (Usuario), vendaPacote (belongsTo), pagamento (hasOne)

## Status (StatusAgendamento enum)

```
Agendado → Confirmado → Finalizado
                      → Cancelado
Agendado → Cancelado
```

## Regras de negocio

### CriarAgendamentoAction
1. Se `fim` nao informado: calcula `inicio + servico.duracao`
2. **Verifica conflito**: busca agendamentos do mesmo atendente que sobrepoe o horario (exceto cancelados)
3. Conflito = `existente.inicio < novo.fim AND existente.fim > novo.inicio`
4. Se conflito: lanca `ConflitoAgendamentoException`
5. Cria com status "agendado"

### CancelarAgendamentoAction
1. Nao pode cancelar se ja "finalizado" (ValidationException)
2. Muda status para "cancelado"
3. Se pagamento existe e esta "pago": estorna (muda para "estornado")

### FinalizarAgendamentoAction
1. Somente se status e "agendado" ou "confirmado"
2. Muda status para "finalizado"

## AgendaController (frontend)

### json()
Retorna eventos para o Toast UI Calendar em JSON:
- Mapeia atendentes para cores (paleta fixa)
- Cada agendamento vira evento com titulo, datas, cor, URL

### index()
- Carrega atendentes e paleta de cores
- Renderiza view com calendario

## Schema: agendamentos

| Coluna | Tipo | Default |
|--------|------|---------|
| id | bigint PK | auto |
| rede_id | FK redes | — |
| empresa_id | FK empresas | — |
| cliente_id | FK clientes | — |
| servico_id | FK servicos | — |
| atendente_id | FK usuarios | — |
| venda_pacote_id | FK vendas_pacote | null |
| inicio | datetime | — |
| fim | datetime | — |
| status | string(20) | 'agendado' |
| observacoes | text | null |
| deleted_at | timestamp | null |

Indices compostos: `[rede_id, empresa_id]`, `[atendente_id, inicio, fim]`
