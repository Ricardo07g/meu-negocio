---
paths:
  - "app/Modules/Tenant/**"
---

# Modulo: Tenant

Nucleo do multi-tenant: gerencia `Rede` (tenant raiz), `Empresa` (N por rede), `Plano`
(preco + limites + feature flags) e a Assinatura (tela "Minha Assinatura", `Fatura`s
mensais internas e troca de plano self-service com pro-rata). Sem gateway de pagamento.

## Entidades & status
- **Rede** (`redes`): tenant raiz. `Model` direto (NAO BaseModel) + SoftDeletes. Fillable `nome, plano_id, status`. Cast `status => App\Enums\StatusRede` (`Ativa`, `Inativa`, `Suspensa`, `Cancelada`). Relacoes: `plano` (belongsTo), `empresas`/`usuarios` (hasMany).
- **Empresa** (`empresas`): BaseModel + SoftDeletes. Fillable `rede_id, nome, documento, telefone, email`. **Sem `empresa_id`/EmpresaTrait** (a propria empresa). `usuarios()` = pivot `empresa_usuario` (N:N, fonte de verdade de acesso); `usuariosDefault()` = `usuarios.empresa_id` (compat). Tambem hasMany clientes, servicos, agendamentos, pagamentos, despesas, produtos.
- **Plano** (`planos`): `Model` direto (sem RedeTrait — e global, compartilhado entre redes). Fillable `nome, preco_mensal, descricao, max_empresas, max_usuarios, tem_estoque, tem_financeiro`. Casts `preco_mensal => decimal:2`, flags boolean. Limite `0 = ilimitado`. Plano `free` buscado por `nome` no seed.
- **Fatura** (`faturas`): BaseModel + SoftDeletes (filtra por `rede_id`). Fillable `rede_id, plano_id, referencia, valor, vencimento, pago_em, status`. `status` e **string** (`em_aberto`, `paga`, `vencida`, `cancelada`) — NAO ha enum `StatusFatura` ainda. `referencia` = `YYYY-MM`. Unique `(rede_id, referencia)` => no maximo 1 fatura por mes por rede.

## Camadas-chave
- `TransicionarPlanoAction::executar(Rede, Plano): Fatura` — valida limites, troca `rede.plano_id`, ajusta fatura do mes pro-rata, tudo em `DB::transaction`.
- `ValidarPlanoAction::executar(Rede, string $recurso)` — limites/flags; lanca `PlanoLimiteException`. Recursos: `empresa`, `usuario`, `estoque`, `financeiro`.
- `CriarEmpresaAction::executar(Rede, EmpresaData): Empresa` — valida limite `empresa` antes de criar.
- `RedeService::criar(CriarRedeData, UsuarioData): Rede` — registro: cria rede (plano free se nao informado), empresa padrao, usuario Admin, e seeds (6 categorias produto, 6 produtos, 6 servicos incl. 1 `etapas`, 5 clientes). Tudo em transacao.
- `EmpresaService` — CRUD (listar/buscar/criar/atualizar/excluir). `PlanoService` — listar/buscar/`verificarLimite` (booleano).
- `AssinaturaController` — `index()` (Minha Assinatura) + `transicionar()` (POST troca de plano).
- `EmpresaController` — resource CRUD de empresas (`except('show')`).
- `FaturaPolicy`: `viewAny` (true — qualquer autenticado ve a propria rede via RedeTrait), `transicionar` (somente `hasRole('Admin')`).
- `EmpresaPolicy`: permissoes `empresa.ver/criar/editar/excluir` + checa `rede_id` e `podeAcessarEmpresa` em update/delete.
- Requests: `TransicionarPlanoRequest` (`plano_id` exists:planos), `SalvarEmpresaRequest` (unificado post/put). DTOs: `EmpresaData`, `CriarRedeData`.

## Regras de negocio / gotchas
- **Pro-rata (ADR-0007)**: `valor = (preco_antigo*dias_decorridos + preco_novo*dias_restantes) / dias_no_mes`, onde `dias_decorridos = hoje->day - 1` e `dias_restantes = dias_no_mes - dias_decorridos` (inclui hoje). Recai sobre a fatura `em_aberto` do mes vigente (UPDATE no mesmo registro — respeita o unique); se nao existir, cria com vencimento no fim do mes. Trocas multiplas no mesmo mes recalculam sobre o preco antigo corrente (sem rateio acumulado) — aceitavel pois nao ha cobranca real.
- **Downgrade bloqueado**: se `uso_empresas > max_empresas` ou `uso_usuarios > max_usuarios` do plano destino, a Action lanca `NegocioException` e nada muda. Trocar para o mesmo plano tambem lanca `NegocioException`.
- **Historico de faturas e gerado sob demanda**: `AssinaturaController::garantirHistoricoFaturas()` cria, ao abrir a tela, uma fatura por mes desde `rede.created_at` ate o mes atual (cap 60 iteracoes). Meses passados ficam `paga` (com `pago_em` simulado); mes vigente `em_aberto` (ou `paga` se vencimento passou). Idempotente pelo unique. E uma demonstracao de portfolio, nao cobranca real.
- Plano e global (sem RedeTrait): nunca aplicar tenancy nele. Rede usa `Model` direto pois e o proprio tenant — escopo seria recursivo.
- Limite `0` em qualquer `max_*` significa ilimitado em todo o codigo (Action, Service, controller).
- Planos seedados (valores podem evoluir): `free` (1 empresa / 2 usuarios, sem estoque/financeiro),
  `basic` (2/5), `pro` (5/10), `business` (ilimitado, com estoque+financeiro).
- Validacao de empresa na criacao acontece na Action via `ValidarPlanoAction`, alem do gate de UI no `EmpresaController::index` (`limite.atingido`).

## Veja tambem
- `.claude/rules/multi-tenant-seguranca.md` — RedeTrait/EmpresaTrait, middlewares (`verificar.rede`, `verificar.empresa`, `verificar.plano:{modulo}`), pivot `empresa_usuario`, sessao de contexto, `PlanoLimiteException`/`NegocioException`.
- `docs/ADR/0007-assinatura-faturamento.md` — decisao de assinatura/faturamento/pro-rata.
