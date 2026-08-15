---
paths:
  - "app/Modules/Agenda/**"
---

# Modulo: Agenda

Agendamentos de servicos por atendente, transacional (com `empresa_id`). UI = Toast UI Calendar
alimentado por JSON. Um agendamento pode pertencer a uma `VendaEtapas` (FK `venda_etapas_id`).

**Agendamento e o fato operacional; venda e o financeiro** (ADR-0018). Um agendamento pode existir
sem venda — reservar nao e vender. A ponte entre os dois e `pagamentos.agendamento_id`, e ela e a
UNICA fonte de verdade sobre "este atendimento virou receita?". Consequencias praticas:
agendamento sem titulo **nao** aparece na listagem de Vendas, e finalizar exige um desfecho.

## Entidades & status
- **Model `Agendamento`** (`agendamentos`): `BaseModel` + `EmpresaTrait`, `RegistraAtividade`,
  `SoftDeletes`. Campos: `cliente_id`, `servico_id`, `atendente_id` (-> `Usuario`),
  `venda_etapas_id` (**renomeado de `venda_pacote_id`**, nullable), `inicio`, `fim` (datetime),
  `status`, `observacoes`, `motivo_sem_cobranca`.
- Relacoes: `cliente`, `servico`, `atendente` (BelongsTo `Usuario`), `vendaEtapas` (BelongsTo),
  `pagamento` (HasOne, FK `agendamento_id` — a ponte com o financeiro).
- Metodos: `foiCobrado()` (existe titulo?) e `situacaoFinanceira()` -> enum
  **`SituacaoFinanceiraAgendamento`** (`ACobrar`, `SemCobranca`, `AReceber`, `Pago`, `Estornado`),
  **derivado do titulo, nunca persistido** — coluna criaria uma segunda verdade que envelhece a
  cada baixa/estorno.
- Enum **`StatusAgendamento`**: `Agendado`, `Confirmado`, `Cancelado`, `Finalizado` (com
  `label()` + `cor()`: info/primary/danger/success). Fluxo:
  `Agendado -> Confirmado -> Finalizado`; `Agendado|Confirmado -> Cancelado`.
- Enum **`MotivoSemCobranca`**: `cortesia`, `retorno`, `garantia`, `interno`.

## Camadas-chave
- **`AgendaController`**: `index` (calendario + atendentes/cores), `json` (eventos+calendars p/ Toast
  UI), `criarRapido` (POST AJAX, cria via Action), `reagendar` (PATCH AJAX, so move inicio/fim),
  `show` (HTML ou JSON), `edit`/`update`, `confirmar`/`finalizar`/`cancelar` (PATCH, aceitam AJAX).
- **`AgendamentoService`**: orquestra; `listarPorPeriodo`, `confirmar` (set Confirmado direto),
  `cancelar`/`finalizar` (delegam Actions), `atualizar`.
- **Actions**: `CriarAgendamentoAction` (calcula `fim` se ausente + detecta conflito + cria
  Agendado), `CancelarAgendamentoAction` (bloqueia se Finalizado; **delega a
  `CaixaService::estornarPagamento`** — mesmo caminho do cancelamento de venda),
  `FinalizarAgendamentoAction` (so de Agendado/Confirmado; **exige desfecho declarado**).
- **`AgendamentoData`** (DTO, campos nullable, inclui `empresa_id`), **`SalvarAgendamentoRequest`**
  (unificado), **`AgendamentoPolicy`** (perms `agendamento.ver|criar|editar|cancelar|excluir`;
  metodo extra `cancel`; checa `rede_id` + `podeAcessarEmpresa`).
- **`App\Support\Agenda\RegrasDeVinculo`**: regras de validacao de cliente/servico/atendente
  escopadas por tenant, usadas pelas DUAS portas de criacao (`SalvarAgendamentoRequest` e
  `AgendaController::criarRapido`) e pela cobranca (`CriarVendaRequest`).

## Regras de negocio / gotchas
- **Deteccao de conflito** (`CriarAgendamentoAction::verificarConflito`): mesmo `atendente_id`,
  status != Cancelado, sobreposicao `existente.inicio < novoFim AND existente.fim > novoInicio`.
  Lanca `ConflitoAgendamentoException`. `reagendar` **nao** revalida conflito.
- `json()` marca eventos Cancelado/Finalizado como `isReadOnly`; cores por atendente vem da paleta
  fixa `$coresAtendente` (10 cores, modulo do indice). Atendentes via
  `Usuario::atendentesDaEmpresa($empresaId)` (contexto) ou `Usuario::where('atende', true)`. O `raw`
  leva tambem `situacao`/`situacao_label`/`situacao_cor` e a `cobrar_url`.
- **Finalizar exige desfecho** (ADR-0018): sem titulo e sem `motivo_sem_cobranca`, a Action lanca
  `NegocioException`. Com titulo (venda pre-paga ou cobranca recem-criada), finaliza direto e o
  motivo e forcado a null — titulo e motivo sao mutuamente exclusivos.
- **Cobrar** (`VendaService::cobrarAtendimento`) cria o titulo E finaliza na mesma transacao. A tela
  e `GET /vendas/nova?agendamento={id}` (modo cobranca), que reusa o bloco de recebimento inteiro.
  Recusa titulo duplicado. Valor sempre de `servico.valor`, nunca do request.
- **Cancelar x estornar**: em aberto, `CancelarAgendamentoAction` cancela E estorna via
  `CaixaService::estornarPagamento` (contra-lancamento por baixa, `estornado_em`, recusa em caixa
  fechado). Ja finalizado, a agenda **recusa** — a reversao vem da tela de Vendas
  (`VendaService::cancelarUnico`), que so estorna e mantem o agendamento Finalizado. Estorno **nao e
  idempotente**: ha guarda contra titulo ja Estornado/Cancelado nos dois caminhos.
- **`exists:` nao respeita global scope.** Vinculos (cliente/servico/atendente) DEVEM passar por
  `RegrasDeVinculo::paraAgendamento(...)`, que escopa por `rede_id` (e pela empresa no atendente).
  Um `exists:clientes,id` cru aceita id de qualquer rede.
- **Falta expediente**: `hourStart: 8 / hourEnd: 21` em `resources/js/calendar.js` e so janela de
  visualizacao do Toast UI. Nao ha validacao de horario no servidor, e `reagendar` **nao revalida
  conflito** — proximo passo, ainda em aberto.

## Veja tambem
- `docs/ADR/0018-agendamento-e-operacao-venda-e-financeiro.md` — a fronteira agenda/financeiro.
- `.claude/rules/modulos/venda.md` — venda de servico unico cria 1 Agendamento; etapas cria N.
- `.claude/rules/modulos/servico.md` — `servico.duracao` calcula `fim`.
- `.claude/rules/multi-tenant-seguranca.md` — `ConflitoAgendamentoException`, contexto de empresa.
