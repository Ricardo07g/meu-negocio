# Meu Negocio - Contexto do Projeto

SaaS multi-tenant para pequenos negocios (clinicas, saloes, massoterapia, autonomos).
Projeto de portfolio, preparado para open source.

> **Conhecimento por contexto (lazy).** Este arquivo e o indice sempre-carregado e enxuto. O detalhe
> vive em `.claude/rules/*.md` (carregados sob demanda via `paths:` quando voce edita arquivos do
> escopo) e nas skills em `.claude/skills/`. Veja a secao **Automacao** no fim.

## Stack
- PHP ^8.3, Laravel ^13.0 · MySQL 8.0 · Redis
- Docker Compose (app, nginx:8080, mysql:3306, redis:6379) — **nao ha PHP no host**; rode tudo via
  `docker exec meu-negocio-app <cmd>`.
- Vite + Tailwind CSS 4 + @toast-ui/calendar ^2.1.3 (Node so no host).
- Template UI: Duralux Admin 1.0.0 (`/home/ricardo/Documentos/Projetos/TEMAS/Duralux-admin-1.0.0/`).

## Pacotes obrigatorios
- `spatie/laravel-permission` ^7.2 (papeis/permissoes) · `spatie/laravel-data` ^4.20 (DTOs) ·
  `spatie/laravel-activitylog` ^4.12 (auditoria).

## Idioma
Tudo em portugues: tabelas, models, controllers, campos, permissoes, rotas.

---

## Arquitetura (visao geral)

- **Estrutura modular**: `app/Modules/{NomeModulo}/` com Controllers, Services, Actions, DTOs,
  Requests, Policies, Models, Views, Migrations.
- **Multi-tenant single-DB**: `rede_id` sempre (`RedeTrait` via `BaseModel`); `empresa_id` no
  transacional (`EmpresaTrait`, Admin ve tudo). Catalogo (Cliente/Servico/Produto/categorias) e
  rede-level; transacional (Agendamento/Venda/Pagamento/Despesa/Caixa/Estoque) e por empresa.
  -> isolamento, ME-010 e camadas de auth em `.claude/rules/multi-tenant-seguranca.md`.
- **Modelo financeiro**: Titulo (`Pagamento`/`Despesa`) + Parcela + Baixa; `forma_pagamento` mora na
  parcela/baixa. -> `.claude/rules/modelo-financeiro.md`.
- **BaseModel**: `App\Models\BaseModel` (Model + `RedeTrait`). Excecoes (Model direto): Plano, Rede,
  MovimentoCaixa. `Usuario` = Authenticatable rede-level. Caixa = BaseModel + EmpresaTrait.

### Padroes de codigo (resumo — detalhe na skill `padroes-projeto`)
- **Controller fino**: request/response + `$this->authorize(...)`, delega a Service/Action;
  `try/catch` por metodo via `tratarErro`; escrita transacional multi-empresa em
  `comEmpresaDeCriacao(...)`.
- **Service**: regra de negocio; transacao via `DB::transaction(fn)` (nunca `DB::` no controller).
- **Request unificado** `SalvarXxxRequest` (distingue por `isMethod('post')`). **DTO unificado**
  `XxxData` (spatie/laravel-data).
- **View**: `_form.blade.php` partial + busca AJAX. -> padroes visuais em `.claude/rules/ui-duralux.md`.
- **Formatacao**: `pint.json` versionado — `declare(strict_types=1)` + imports agrupados/ordenados.
  Rode `vendor/bin/pint`.
- **Commits**: `tipo(modulo): mensagem` (feat/fix/refactor/docs/chore/test).

---

## Modulos — completos
Auth, Tenant (Rede/Empresa/Plano), Usuario, Perfil (Meu Perfil), PerfilAcesso, Cliente, Servico,
Agenda, Pagamento, Despesa, Estoque, Produto, Venda (VendaEtapas + VendaProduto), Caixa, Dashboard,
Assinatura (troca de plano pro-rata, sem gateway — ADR-0007).
-> dominio de cada modulo em `.claude/rules/modulos/{modulo}.md` (lazy).

## Banco de Dados — tabelas
planos, redes, empresas, usuarios, clientes, servicos, agendamentos, vendas_etapas, vendas_produto,
venda_produto_itens, **pagamentos, parcelas_pagamento, baixas_pagamento**, **despesas,
parcelas_despesa, baixas_despesa, categorias_despesa**, produtos, categorias_produto,
movimentos_estoque, caixas, movimentos_caixa, **faturas**.
-> convencoes de migration em `.claude/rules/banco-de-dados.md` e skill `criar-migration`.

## Traits
| Trait | Uso |
|---|---|
| RedeTrait | Global scope rede_id (via BaseModel) |
| EmpresaTrait | Global scope empresa_id (Admin ve tudo) |
| RegistraAtividade | Spatie ActivityLog |
| TratamentoErros | Error handling controllers (`tratarErro`) |
| DefineEmpresaDeCriacao | Helper `comEmpresaDeCriacao` (contexto ME-010 em escrita) |

## Seeds (ao registrar)
Categorias, produtos, servicos, clientes padrao criados automaticamente ao registrar nova rede.

---

## Testes
- `tests/Feature/` por contexto (Auth, Venda, Pagamento, Caixa, MultiTenant, MultiEmpresa, Usuario,
  Produto, Servico, Estoque, Despesa, Agenda, Dashboard, PerfilAcesso, Tenant) + `AuditoriaTest`,
  `_FactoriesSmokeTest`.
- **141 testes Feature** (510 asserts) cobrindo CRUD, isolamento multi-tenant/empresa, autorizacao
  (403), fluxos financeiros, estoque, agenda, dashboard e plano pro-rata.
- `composer test` em **SQLite in-memory** (`phpunit.xml`). Models **NAO usam `HasFactory`** —
  instancie via `XxxFactory::new()->create([...])`. Trait `tests/Concerns/CriaTenant.php`.
- Skills `gerar-teste-model` (escrever testes/factories) e `validar-implementacao` (validar uma
  feature ponta-a-ponta: testes + pint + phpstan + smoke).

## CI/CD
- `.github/workflows/ci.yml` em `push`/`pull_request` para `main`: setup PHP 8.3 (pcov) -> composer
  install -> key:generate -> **PHPStan** (nivel 5 + baseline) -> **testes** (`--coverage --min=30`)
  -> **Pint** (`--test`) -> **sync devkit** (`bin/sync-devkit.sh --check`).
- Skill `checklist-pre-pr` + comando `/pre-pr` rodam a porta de qualidade localmente.

## Documentacao
- `README.md` (portfolio), `CONTRIBUTING.md`, `docs/ADR/` (7 ADRs), `docs/AUTOMACAO.md` (esta
  automacao), `docs/FECHAMENTO_PORTFOLIO.md` e `docs/FASE_1_5_MULTI_EMPRESA.md` (historicos).

---

## Automacao de Desenvolvimento (Claude Code)

Fonte canonica em `.claude/` (versionado, auto-descoberto); espelhada como plugin em `devkit/` via
`bin/sync-devkit.sh` (o CI valida a sincronia). Detalhes em `docs/AUTOMACAO.md`. Execucao sempre no
container (`docker exec meu-negocio-app <cmd>`).

- **Rules lazy** (`.claude/rules/`): conhecimento ativado por `paths:` —
  `multi-tenant-seguranca`, `modelo-financeiro`, `ui-duralux`, `banco-de-dados`, `modulos/{modulo}`,
  `fluxos`. Carregam so ao editar arquivos do escopo (mantem o contexto enxuto).
- **Skills** (`.claude/skills/`): `padroes-projeto`, `scaffold-modulo`, `gerar-teste-model`,
  `checklist-pre-pr`, `validar-implementacao`, `revisar-codigo`, `depurar`, `criar-migration`,
  `adicionar-permissao`, `documentar-adr`, `escrever-commit`.
- **Subagents** (`.claude/agents/`): `laravel-test-writer`, `laravel-module-scaffolder`,
  `tenancy-security-reviewer`, `tech-product-owner` (+ global `laravel-senior-architect`).
- **Slash commands** (`.claude/commands/`): `/testar`, `/migrar`, `/auditar-tenancy`, `/pre-pr`.
- **Hooks** (`.claude/settings.json`): Pint ao editar `.php`, bloqueio de `.env`, lembrete de
  `down()` em migrations.

---

## Regras para IA
1. **Buscar padroes visuais** no Duralux antes de criar UI.
2. **Perguntar antes** de criar algo grande.
3. **Nunca pular etapas.**
4. **Validar sempre**: auth, tenant, permissao, plano.
5. **Nunca** permitir acesso cruzado entre redes/empresas.
6. **Requests unificados** (SalvarXxxRequest) · **DTOs unificados** (XxxData).
7. **Views com partial** (_form.blade.php + $entidade) · **busca AJAX** (nunca select com tudo).
8. **Models**: BaseModel + secoes ASCII art.
9. **Apos implementar**: valide com a skill `validar-implementacao` (testes + pint + phpstan + smoke).
