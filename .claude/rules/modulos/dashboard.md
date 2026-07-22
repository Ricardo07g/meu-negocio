---
paths:
  - "app/Modules/Dashboard/**"
---

# Modulo: Dashboard

Painel inicial pos-login. Apenas leitura: agrega indicadores de varios modulos
(agenda, financeiro, caixa, catalogo) em cards, listas e graficos. Sem escrita.

## Entidades & status
Nao tem model proprio. Le de: `Agendamento` (status `App\Enums\StatusAgendamento`),
`Cliente`, `Servico` (catalogo de rede), `ParcelaPagamento` (`StatusParcela`),
`BaixaPagamento`/`BaixaDespesa`/`Caixa` (`StatusCaixa`), `Conta` (saldo por conta) e
`Recebivel` (previstos a cair).

## Camadas-chave
- `DashboardController::index(): View|RedirectResponse` — metodo unico; passa `DashboardService::indicadores()` para `dashboard::dashboard`. Sem Policy/permissao (qualquer autenticado com rede+empresa via middleware). Rota: `GET dashboard` (`name('dashboard')`), dentro de `verificar.empresa` + `aplicar.contexto.empresa`.
- `DashboardService::indicadores(): array` — monta todas as chaves consumidas pela view. Metodos:
  - `agendamentosHoje()`, `proximosAgendamentos($limite=5)` (status Agendado/Confirmado, hoje a partir de agora), `agendamentosPorStatusMes()` (donut, todos os `StatusAgendamento::cases()` com label/cor).
  - `receitaMes()`/`receitaMesAnterior()` (soma `BaixaPagamento.valor` por mes), `despesaMes()`/`despesaMesAnterior()` (`BaixaDespesa.valor`), `fluxoUltimos6Meses()` (receita x despesa por mes, label `MMM/YY` pt_BR).
  - `contasReceberQuantidade()`/`contasReceberTotal()` (parcelas `Pendente`; total = `SUM(valor - valor_pago)`), `caixaAberto(): ?Caixa`.
  - `saldoPorConta(): Collection<Conta>` (contas ativas com saldo atual, caixa-padrao primeiro — card "Saldo por conta") e `recebiveisACair(): float` (`Recebivel::previstos()->sum('valor_liquido')` — card "Recebiveis a cair", dinheiro a caminho fora do saldo realizado).
  - `totalClientes()`, `servicosAtivos()`.

## Regras de negocio / gotchas
- **Tenancy e automatica pelos models** — o service NAO filtra `empresa_id`/`rede_id` manualmente; confia nos global scopes (RedeTrait/EmpresaTrait). Cards transacionais (agendamentos, baixas, parcelas, caixa) respeitam `empresa_contexto_atual`/`empresas_atuais` via EmpresaTrait.
- **`totalClientes()` e `servicosAtivos()` sao por REDE de proposito** — Cliente e Servico sao catalogo de rede (sem `empresa_id`), entao contam toda a rede, nao a empresa em contexto. Comportamento intencional (docblocks no service marcam isso).
- `parcelasVencendo()` cobre so "a receber" (`ParcelaPagamento`, status Pendente/Vencido, vencimento entre hoje e +7 dias). Despesa fica de fora por decisao de simplicidade visual.
- `caixaAberto()` retorna 1 unico caixa (o aberto da empresa em contexto) ou null — coerente com 1 caixa por empresa/dia.
- Mes anterior usa `subMonthNoOverflow()` (evita pular para 2 meses em dias 29-31).
- Atencao: o Dashboard NAO e "implementacao minima". Ha um `DashboardService` com agregacoes reais
  (cards, proximos agendamentos, parcelas vencendo, grafico de 6 meses, donut de status) — nao o
  trate como stub ao mexer aqui.

## Veja tambem
- `.claude/rules/multi-tenant-seguranca.md` — por que nao filtrar tenant na mao (global scopes), catalogo (rede) x transacional (empresa).
- `.claude/rules/modulos/caixa.md`, `conta.md`, `pagamento.md`, `agenda.md` — fonte dos dados agregados.
