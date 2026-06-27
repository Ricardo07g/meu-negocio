---
paths:
  - "app/Modules/Tenant/**"
---

# Modulo: Tenant

Nucleo do multi-tenant: gerencia `Rede` (tenant raiz), `Empresa` (N por rede), `Plano`
(preco + limites + feature flags) e a Assinatura (tela "Minha Assinatura", `Fatura`s
mensais internas e troca de plano self-service com pro-rata). Sem gateway de pagamento.

## Entidades & status
- **Rede** (`redes`): tenant raiz. `Model` direto (NAO BaseModel) + SoftDeletes. Fillable `nome, plano_id, plano_agendado_id, status`. Cast `status => App\Enums\StatusRede` (`Ativa`, `Inativa`, `Suspensa`, `Cancelada`). `plano_agendado_id` (FK `planos` nullable, `nullOnDelete`) guarda um downgrade agendado para a virada do mes (ADR-0008). Relacoes: `plano`/`planoAgendado` (belongsTo), `empresas`/`usuarios` (hasMany).
- **Empresa** (`empresas`): BaseModel + SoftDeletes. Fillable `rede_id, nome, documento, telefone, email`. **Sem `empresa_id`/EmpresaTrait** (a propria empresa). `usuarios()` = pivot `empresa_usuario` (N:N, fonte de verdade de acesso); `usuariosDefault()` = `usuarios.empresa_id` (compat). Tambem hasMany clientes, servicos, agendamentos, pagamentos, despesas, produtos.
- **Plano** (`planos`): `Model` direto (sem RedeTrait — e global, compartilhado entre redes). Fillable `nome, preco_mensal, descricao, max_empresas, max_usuarios, tem_estoque, tem_financeiro`. Casts `preco_mensal => decimal:2`, flags boolean. Limite `0 = ilimitado`. Plano `free` buscado por `nome` no seed.
- **Fatura** (`faturas`): BaseModel + SoftDeletes (filtra por `rede_id`). Fillable `rede_id, plano_id, referencia, valor, vencimento, pago_em, status`. `status` = cast `App\Enums\StatusFatura` (`EmAberto`, `Paga`, `Vencida`, `Cancelada`; `label()`/`cor()`) — enum PHP sobre coluna `string(20)` (sem migration de dados). `referencia` = `YYYY-MM`. Unique `(rede_id, referencia)` => no maximo 1 fatura por mes por rede.

## Camadas-chave
- `TransicionarPlanoAction::executar(Rede, Plano): ResultadoTransicao` (ADR-0008) — valida limites e distingue upgrade (imediato: troca `rede.plano_id` + ajusta fatura do mes **so se `em_aberto`**, em `DB::transaction`) de downgrade (agenda em `rede.plano_agendado_id`, sem mexer no plano/fatura). Escolher o plano atual com agendamento ativo cancela o agendamento. Retorna o DTO `ResultadoTransicao` (`mensagem()` por tipo).
- `App\Modules\Tenant\Support\CalculadoraProRata::calcular(precoAntigo, precoNovo, ?hoje): float` — fonte unica da formula pro-rata, usada pela Action (efeito) e pelo controller (previa).
- `ValidarPlanoAction::executar(Rede, string $recurso)` — limites/flags; lanca `PlanoLimiteException`. Recursos: `empresa`, `usuario`, `estoque`, `financeiro`.
- `CriarEmpresaAction::executar(Rede, EmpresaData): Empresa` — valida limite `empresa` antes de criar.
- `RedeService::criar(CriarRedeData, UsuarioData): Rede` — registro: cria rede (plano free se nao informado), empresa padrao, usuario Admin, e seeds (6 categorias produto, 6 produtos, 6 servicos incl. 1 `etapas`, 5 clientes). Tudo em transacao.
- `EmpresaService` — CRUD (listar/buscar/criar/atualizar/excluir). `PlanoService` — listar/buscar/`verificarLimite` ("posso adicionar +1?") e `cabeNoPlano(Rede, Plano)` ("uso atual <= limites?", usado na virada).
- `AssinaturaController` — `index()` (Minha Assinatura; aplica downgrade agendado na virada via `aplicarPlanoAgendadoSeViravelMes()` e monta `$previas`), `transicionar()` (POST troca) e `pagar(Fatura)` (POST marcar fatura como paga).
- `EmpresaController` — resource CRUD de empresas (`except('show')`).
- `FaturaPolicy`: `viewAny` (true — qualquer autenticado ve a propria rede via RedeTrait), `transicionar` e `pagar` (somente `hasRole('Admin')`).
- Rotas: `assinatura.index`, `assinatura.transicionar`, `assinatura.fatura.pagar` (`{fatura}` route-model binding, isolado pelo RedeTrait).
- `EmpresaPolicy`: permissoes `empresa.ver/criar/editar/excluir` + checa `rede_id` e `podeAcessarEmpresa` em update/delete.
- Requests: `TransicionarPlanoRequest` (`plano_id` exists:planos), `SalvarEmpresaRequest` (unificado post/put). DTOs: `EmpresaData`, `CriarRedeData`.

## Regras de negocio / gotchas
- **Upgrade x downgrade (ADR-0008)**: upgrade (preco destino >= atual, preco igual conta como upgrade) e **imediato**; downgrade (preco destino < atual) e **agendado** para o proximo ciclo (`rede.plano_agendado_id`), sem reembolso. Matriz: upgrade+fatura `EmAberto` -> ajusta pro-rata (UPDATE no mesmo registro); upgrade+fatura `Paga`/`Vencida` -> NAO toca a fatura (vale na proxima); upgrade sem fatura do mes -> cria pro-rata; downgrade -> so agenda. **Nunca sobrescrever fatura paga/vencida.**
- **Pro-rata**: `valor = (preco_antigo*dias_decorridos + preco_novo*dias_restantes) / dias_no_mes`, `dias_decorridos = hoje->day - 1`, `dias_restantes = dias_no_mes - dias_decorridos`. Centralizado em `CalculadoraProRata::calcular`.
- **Downgrade bloqueado**: se `uso_empresas > max_empresas` ou `uso_usuarios > max_usuarios` do destino, a Action lanca `NegocioException` e nada e agendado. Trocar para o mesmo plano **sem** agendamento lanca `NegocioException`; **com** agendamento, cancela o agendamento (sem erro).
- **Aplicacao do agendamento e lazy**: `AssinaturaController::aplicarPlanoAgendadoSeViravelMes()` roda ao abrir a tela; quando a ultima fatura e de um mes anterior ao atual, promove `plano_agendado_id -> plano_id` (revalidando limites com `cabeNoPlano`; se nao cabe mais, cancela + log). Sem scheduler real — meses pulados ficam no plano novo. Demonstracao de portfolio.
- **Historico de faturas e gerado sob demanda**: `AssinaturaController::garantirHistoricoFaturas()` cria, ao abrir a tela, uma fatura por mes desde `rede.created_at` ate o mes atual (cap 60 iteracoes). Meses passados ficam `Paga` (com `pago_em` simulado); mes vigente `EmAberto` (ou `Paga` se vencimento passou). Idempotente pelo unique. `pagar()` marca uma fatura `EmAberto`/`Vencida` como `Paga` (Admin).
- Plano e global (sem RedeTrait): nunca aplicar tenancy nele. Rede usa `Model` direto pois e o proprio tenant — escopo seria recursivo.
- Limite `0` em qualquer `max_*` significa ilimitado em todo o codigo (Action, Service, controller).
- Planos seedados (valores podem evoluir): `free` (1 empresa / 2 usuarios, sem estoque/financeiro),
  `basic` (2/5), `pro` (5/10), `business` (ilimitado, com estoque+financeiro).
- Validacao de empresa na criacao acontece na Action via `ValidarPlanoAction`, alem do gate de UI no `EmpresaController::index` (`limite.atingido`).

## Veja tambem
- `.claude/rules/multi-tenant-seguranca.md` — RedeTrait/EmpresaTrait, middlewares (`verificar.rede`, `verificar.empresa`, `verificar.plano:{modulo}`), pivot `empresa_usuario`, sessao de contexto, `PlanoLimiteException`/`NegocioException`.
- `docs/ADR/0007-assinatura-faturamento.md` — assinatura/faturamento/pro-rata (base, substituido em parte).
- `docs/ADR/0008-transicao-plano-upgrade-downgrade.md` — upgrade imediato x downgrade agendado, enum de status, marcar como paga.
