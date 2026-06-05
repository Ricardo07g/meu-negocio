---
paths:
  - "app/Modules/Agenda/**"
---

# Modulo: Agenda

Agendamentos de servicos por atendente, transacional (com `empresa_id`). UI = Toast UI Calendar
alimentado por JSON. Um agendamento pode pertencer a uma `VendaEtapas` (FK `venda_etapas_id`).

## Entidades & status
- **Model `Agendamento`** (`agendamentos`): `BaseModel` + `EmpresaTrait`, `RegistraAtividade`,
  `SoftDeletes`. Campos: `cliente_id`, `servico_id`, `atendente_id` (-> `Usuario`),
  `venda_etapas_id` (**renomeado de `venda_pacote_id`**, nullable), `inicio`, `fim` (datetime),
  `status`, `observacoes`.
- Relacoes: `cliente`, `servico`, `atendente` (BelongsTo `Usuario`), `vendaEtapas` (BelongsTo),
  `pagamento` (HasOne, FK `agendamento_id` — usado na venda de servico unico).
- Enum **`StatusAgendamento`**: `Agendado`, `Confirmado`, `Cancelado`, `Finalizado` (com
  `label()` + `cor()`: info/primary/danger/success). Fluxo:
  `Agendado -> Confirmado -> Finalizado`; `Agendado|Confirmado -> Cancelado`.

## Camadas-chave
- **`AgendaController`**: `index` (calendario + atendentes/cores), `json` (eventos+calendars p/ Toast
  UI), `criarRapido` (POST AJAX, cria via Action), `reagendar` (PATCH AJAX, so move inicio/fim),
  `show` (HTML ou JSON), `edit`/`update`, `confirmar`/`finalizar`/`cancelar` (PATCH, aceitam AJAX).
- **`AgendamentoService`**: orquestra; `listarPorPeriodo`, `confirmar` (set Confirmado direto),
  `cancelar`/`finalizar` (delegam Actions), `atualizar`.
- **Actions**: `CriarAgendamentoAction` (calcula `fim` se ausente + detecta conflito + cria
  Agendado), `CancelarAgendamentoAction` (bloqueia se Finalizado; estorna pagamento Pago->Estornado),
  `FinalizarAgendamentoAction` (so de Agendado/Confirmado).
- **`AgendamentoData`** (DTO, campos nullable, inclui `empresa_id`), **`SalvarAgendamentoRequest`**
  (unificado), **`AgendamentoPolicy`** (perms `agendamento.ver|criar|editar|cancelar|excluir`;
  metodo extra `cancel`; checa `rede_id` + `podeAcessarEmpresa`).

## Regras de negocio / gotchas
- **Deteccao de conflito** (`CriarAgendamentoAction::verificarConflito`): mesmo `atendente_id`,
  status != Cancelado, sobreposicao `existente.inicio < novoFim AND existente.fim > novoInicio`.
  Lanca `ConflitoAgendamentoException`. `reagendar` **nao** revalida conflito.
- `json()` marca eventos Cancelado/Finalizado como `isReadOnly`; cores por atendente vem da paleta
  fixa `$coresAtendente` (10 cores, modulo do indice). Atendentes via
  `Usuario::atendentesDaEmpresa($empresaId)` (contexto) ou `Usuario::where('atende', true)`.
- Cancelar agendamento estorna so o pagamento "simples" (HasOne em `agendamento_id`) marcando
  status Estornado — **nao** usa `CaixaService::estornarPagamento` (isso e do fluxo Venda). Nao move
  caixa nem devolve estoque.

## Veja tambem
- `.claude/rules/modulos/venda.md` — venda de servico unico cria 1 Agendamento; etapas cria N.
- `.claude/rules/modulos/servico.md` — `servico.duracao` calcula `fim`.
- `.claude/rules/multi-tenant-seguranca.md` — `ConflitoAgendamentoException`, contexto de empresa.
