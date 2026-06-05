# Meu Negocio - Contexto do Projeto

SaaS multi-tenant para pequenos negocios (clinicas, saloes, massoterapia, autonomos).
Projeto de portfolio, preparado para open source.

## Stack

- PHP ^8.3, Laravel ^13.0
- MySQL 8.0, Redis
- Docker Compose (app, nginx:8080, mysql:3306, redis:6379)
- Vite + Tailwind CSS 4 + @toast-ui/calendar ^2.1.3
- Template: Duralux Admin 1.0.0 (`/home/ricardo/Documentos/Projetos/TEMAS/Duralux-admin-1.0.0/`)

## Pacotes Obrigatorios

- `spatie/laravel-permission` ^7.2 — papeis e permissoes
- `spatie/laravel-data` ^4.20 — DTOs
- `spatie/laravel-activitylog` ^4.12 — auditoria

## Idioma

Tudo em portugues: tabelas, models, controllers, campos, permissoes, rotas.

---

## Interface / UI

### Template de referencia
Sempre buscar padroes de interface no Duralux Admin:
`/home/ricardo/Documentos/Projetos/TEMAS/Duralux-admin-1.0.0/`

Componentes: cards, tables, badges, buttons, forms Bootstrap 5. Icones: Feather (`feather-*`). Modais: SweetAlert2.

### Padroes de UI consolidados
- **Formularios CRUD**: `_form.blade.php` partial compartilhado (create/edit), recebe `$entidade` (null no create)
- **Botoes de formulario**: `<x-form-botoes>` component (Voltar/Salvar, min-width 300px)
- **Botao Voltar em show**: `btn btn-light px-5 py-2` com `min-width: 300px`
- **Busca de entidades**: AJAX com `initAjaxSearch()` (funcao global no layout), nunca carregar tudo em select
- **Tabelas**: `table table-hover` ou `table table-striped table-hover`
- **Badges**: bg-success (ativo/pago), bg-warning (pendente), bg-danger (cancelado), bg-secondary (estornado)
- **Modais SweetAlert**: inputs com `width:100%;max-width:100%;box-sizing:border-box;`, textareas `rows="3"`, cor `#3454d1`
- **Models**: secoes ASCII art (RELATIONS, ACESSORS, MUTATORS, SCOPES, METHODS)

### AJAX Search (global no layout)
Endpoints: `GET clientes/buscar?q=`, `GET produtos/buscar?q=`, `GET servicos/buscar?q=`

---

## Arquitetura

### Multi-tenant
Single DB + tenant_id. Traits: `RedeTrait` (rede_id), `EmpresaTrait` (empresa_id, Admin ve tudo).

### Multi-empresa (Fase 1.5)
Uma `Rede` tem N `Empresa`s. Regra de escopo:
- **Catalogo (rede):** Cliente, Servico, Produto, CategoriaProduto, CategoriaDespesa — sem `empresa_id`. Compartilhados entre empresas da rede.
- **Transacional (empresa):** Agendamento, Venda, Pagamento, Despesa, Caixa, Estoque — com `empresa_id`. Isolados por empresa.

Modelo de acesso do usuario:
- **`Usuario` e entidade rede-level** (`RedeTrait` apenas — NAO usa `EmpresaTrait`). Aplicar EmpresaTrait em `Usuario` quebraria `auth()->user()` quando o contexto vigente fosse diferente do `usuario.empresa_id` default.
- `usuarios.empresa_id` = empresa default ao logar (preferencia, mantida por compat). NAO e barreira de tenancy.
- Pivot `empresa_usuario` (`rede_id`, `empresa_id`, `usuario_id`) = fonte de verdade do conjunto de empresas que um usuario pode acessar.
- Admin (`hasRole('Admin')`) acessa todas as empresas da rede automaticamente — pivot dispensavel.
- Validacao no `SalvarUsuarioRequest` exige >=1 empresa para nao-admin.
- Listagens de atendentes (Agenda, Venda) usam scope `Usuario::atendentesDaEmpresa($empresaId)` que filtra via pivot `empresa_usuario` (ou Role `Admin`). Helper `App\Support\ContextoEmpresa::resolver()` retorna o id da empresa em contexto (URL > sessao com 1 empresa) ou null.

Selecao corrente:
- `session('empresas_atuais')` armazena os IDs de empresas acessiveis ao usuario.
- Middleware `VerificarEmpresa` popula a sessao em todo request (Admin = todas as empresas da rede; nao-admin = pivot `empresa_usuario`) e poda IDs invalidos.
- Nao ha mais seletor manual no header — a sessao reflete sempre o universo total acessivel ao usuario.
- `EmpresaTrait` filtra `WHERE empresa_id IN (...)` priorizando contexto da listagem; senao, `empresas_atuais`.

Operacao com multiplas empresas (modelo ME-010 v3):
- **`empresas_atuais`** (sessao, multi): universo acessivel — sempre todas que o usuario pode ver, nao editavel manualmente.
- **`empresa_contexto_atual`** (sessao, single int): contexto vigente da listagem. Define a empresa-base para criar registros e filtrar listagem.
- **Filtro de listagem** (`partials/filtro-empresa-listagem.blade.php`) presente como primeira coluna do filter form em Venda/Pagamento/Despesa/Estoque, e standalone em Agenda/Caixa (que nao tem filter form). Submit do form leva `?empresa_id=X` na URL → middleware `aplicar.contexto.empresa` interpreta o param:
  - `?empresa_id=X` (X em `empresas_atuais`): seta `session('empresa_contexto_atual') = X`.
  - `?empresa_id=todas`: limpa contexto.
  - sem param: respeita contexto existente; poda se ficou stale.
- **EmpresaTrait** prioriza `empresa_contexto_atual` sobre `empresas_atuais` no scope e no `creating`. Forms criados a partir da listagem herdam empresa do contexto silenciosamente — sem precisar de selector proprio.
- **Caixa Diario** exige 1 empresa unica: aceita contexto (URL) OU 1 empresa no header. Com varias no header sem contexto, exibe aviso pedindo escolha.
- **Pagamento e Despesa (baixa/renegociar/cancelar parcela)**: defesa em profundidade — controllers setam `session('empresa_criacao_atual', $parcela->empresa_id)` no try e fazem `forget()` no finally. Garante que `BaixaPagamento`/`BaixaDespesa` (que tem `empresa_id NOT NULL`) tenha o id correto mesmo se o usuario chegou via link direto sem passar pela listagem.
- **Sub-seletor visual** (`partials/sub-seletor-empresa.blade.php`) ainda existe em modo `visualizar` nas telas de baixa para deixar explicito a empresa da parcela.
- Helper `Usuario::podeAcessarEmpresa(?int)` usado por todas as Policies.

### BaseModel
`App\Models\BaseModel` extends Model + usa `RedeTrait`. Todos models tenant-aware estendem BaseModel.
Excecoes: Plano, Rede, MovimentoCaixa (Model direto). Usuario (Authenticatable + traits direto).
Caixa usa BaseModel + EmpresaTrait (isolamento por empresa alem da rede).

### Estrutura Modular
`app/Modules/{NomeModulo}/` com Controllers, Services, Actions, DTOs, Requests, Policies, Models, Views, Migrations.

### Padroes de Codigo
- Controller: request/response apenas, chama service
- Service: regra de negocio
- **Requests unificados**: `SalvarXxxRequest` (isMethod('post') para criar/editar)
- **DTOs unificados**: `XxxData` (um para criar e atualizar)
- **Views com partial**: `_form.blade.php` + `@php $entidade = $entidade ?? null; @endphp`

---

## Modulos — Estado Atual

### Completos
- **Auth** — Login, Registrar, Logout, Reset de Senha, Rate Limit em login/registro
- **Tenant** — Rede, Empresa, Plano
- **Usuario** — CRUD completo
- **Perfil (Meu Perfil)** — Self-service: dados pessoais + troca de senha (`GET/POST /perfil`, `POST /perfil/senha`)
- **PerfilAcesso** — CRUD de papeis e permissoes (renomeado de Papel; validacao dinamica via `exists:roles,name`)
- **Cliente** — CRUD + Actions + busca AJAX
- **Servico** — CRUD, tipos avulso/pacote + busca AJAX
- **Agenda** — CRUD + confirmar/finalizar/cancelar, Toast UI Calendar
- **Pagamento** — Titulo+Parcelas, baixa parcial por parcela, renegociacao, cancelamento, contas a receber, recibo
- **Despesa** — Titulo+Parcelas, categorias, baixa parcial por parcela, recibo
- **Estoque** — Movimentos entrada/saida/ajuste
- **Produto** — CRUD + CategoriaProduto (descricao + ativo) + busca AJAX
- **Venda** — VendaEtapas + VendaProduto (carrinho multi-item) + estorno automatico
- **Caixa** — Navegacao por dia, abrir/fechar/reabrir, sangria/reforco, retroativo
- **Dashboard** — Cards reais + listas de proximos agendamentos e parcelas a vencer (agregacoes em `DashboardService`)
- **Assinatura** — Tela "Minha Assinatura" (plano, uso x limites, fatura do mes, historico), troca de plano self-service (Admin) com validacao de limites e fatura pro-rata (`TransicionarPlanoAction`). Sem gateway (faturas internas). Ver ADR-0007.

---

## Banco de Dados

### Tabelas
planos, redes, empresas, usuarios, clientes, servicos, agendamentos, vendas_etapas, vendas_produto, venda_produto_itens, **pagamentos, parcelas_pagamento, baixas_pagamento**, **despesas, parcelas_despesa, baixas_despesa, categorias_despesa**, produtos, categorias_produto, movimentos_estoque, caixas, movimentos_caixa, **faturas**

---

## Fluxos de Negocio

### Modelo financeiro: Titulo + Parcela
- **Titulo** = `Pagamento` (a receber) ou `Despesa` (a pagar). Contem `condicao_pagamento`, `forma_recebimento_prazo`, valor bruto/liquido, referencia ao originador (venda, despesa avulsa).
- **Parcela** = `ParcelaPagamento` / `ParcelaDespesa`. Tem `numero`, `data_vencimento`, `valor`, `valor_pago`, `status` (`StatusParcela` — ver enums abaixo), `forma_pagamento` (preenchida na baixa).
- **Baixa** = `BaixaPagamento` / `BaixaDespesa`. Vincula parcela + caixa + valor + multa/juros/desconto. Uma parcela pode ter N baixas.
- Geracao de parcelas: `App\Support\Parcelamento\CalculadoraParcelas`.

### Enums do modelo
- `CondicaoPagamento`: `a_vista`, `a_prazo`, `boleto`, `pix_parcelado`
- `FormaRecebimentoPrazo`: canais esperados de recebimento do titulo a prazo
- `StatusParcela`: `Pendente`, `Pago`, `Vencido`, `Cancelado`, `Renegociado`
- `FormaPagamento`: pix, dinheiro, cartao etc. (na parcela/baixa, NAO no titulo)

### Venda → Pagamento → Caixa
- A vista → `CriarPagamentoComParcelasAction` cria Pagamento + 1 parcela e baixa automaticamente via `CaixaService::darBaixaParcelaPagamento` (exige caixa aberto, pre-validado no controller antes da transacao)
- A prazo → cria Pagamento + N parcelas status Pendente → aparecem em Contas a Receber → baixa por parcela (forma real na baixa, exige caixa aberto)
- `forma_pagamento` **fica na parcela/baixa**, nao no titulo. O que indica fiado agora e `condicao_pagamento = a_prazo`.

### Estorno ao Cancelar
- `CaixaService::estornarPagamento`: marca parcelas Pendente->Cancelado, cria MovimentoCaixa(saida) com `valorPago()`, seta Pagamento.status=Estornado. Estoque devolvido e agendamentos cancelados pelo VendaService.

### Caixa Diario
- Navegacao prev/next por dia (`?data=YYYY-MM-DD`), 1 caixa por empresa/dia, permite retroativo. Reabertura via `ReabrirCaixaData`/`ReabrirCaixaRequest`.

---

## Traits
| Trait | Uso |
|---|---|
| RedeTrait | Global scope rede_id (via BaseModel) |
| EmpresaTrait | Global scope empresa_id (Admin ve tudo) |
| RegistraAtividade | Spatie ActivityLog |
| TratamentoErros | Error handling controllers |

---

## Seeds (ao registrar)
Categorias, produtos, servicos, clientes padrao criados automaticamente ao registrar nova rede.

---

## Testes

- `tests/Feature/` agrupado por contexto: `Auth/`, `Venda/`, `Pagamento/`, `Caixa/`, `MultiTenant/`, `MultiEmpresa/`, `Usuario/`, `Produto/`, `Servico/`, `Estoque/`, `Despesa/`, `Agenda/`, `Dashboard/`, `PerfilAcesso/`, `Tenant/` (assinatura), alem de `AuditoriaTest.php` e `_FactoriesSmokeTest.php`.
- **117 testes Feature** (439 asserts) cobrindo CRUD, isolamento multi-tenant/multi-empresa, autorizacao (403), fluxos financeiros (baixa/estorno), movimentacao de estoque, agendamentos, dashboard e troca de plano pro-rata.
- `composer test` roda em **SQLite in-memory** (config no `phpunit.xml`).
- Factories em `database/factories/` para todos os models principais. **Os models NAO usam `HasFactory`** (namespace modular) — instancie via `XxxFactory::new()->create([...])`, nunca `Model::factory()`.
- Trait `tests/Concerns/CriaTenant.php`: `criarRedeAutenticada()`, `criarRede()`, `criarUsuarioComum()`, `garantirSeedsBase()`.
- Qualidade: `vendor/bin/pint --test` (zero diffs) e `composer stan` (PHPStan/Larastan nivel 5 + baseline). Veja a skill `checklist-pre-pr` e o comando `/pre-pr`.

---

## CI/CD

- `.github/workflows/ci.yml` roda em `push` e `pull_request` para `main`.
- Steps: setup PHP 8.3 (com `pdo_sqlite`, `redis`, `bcmath`, `gd`, `coverage: pcov`) -> `composer install` -> `php artisan key:generate` -> **PHPStan/Larastan** (`vendor/bin/phpstan analyse`) -> **testes com cobertura** (`php artisan test --coverage --min=30`) -> `vendor/bin/pint --test`.
- Cache de pacotes Composer entre execucoes via `actions/cache@v4`.
- Analise estatica: `phpstan.neon` (nivel 5, paths `app/`) + `phpstan-baseline.neon` (erros legados congelados; o gate barra apenas erros novos). `--min` de cobertura e piso conservador, a elevar.

---

## Documentacao

- `README.md` — peca de portfolio com setup Docker, screenshots e overview do produto.
- `CONTRIBUTING.md` — guia de contribuicao (padroes de commit, fluxo de PR, padroes de codigo).
- `docs/ADR/` — 7 ADRs de decisoes arquiteturais (multi-tenant single-DB, modelo financeiro Titulo+Parcela+Baixa, estrutura modular, BaseModel + traits, caixa diario com retroativo, FKs cascade/null/restrict, assinatura/faturamento pro-rata). Indice em `docs/ADR/README.md`.
- `docs/AUTOMACAO.md` — automacao de desenvolvimento via Claude Code (hooks, subagents, skills, slash commands) e o plugin distribuivel em `devkit/`.
- `docs/FECHAMENTO_PORTFOLIO.md` — backlog do fechamento de portfolio (historico das Fases 1 a 5 + closure).
- `docs/FASE_1_5_MULTI_EMPRESA.md` — backlog da fase multi-empresa N:N (historico de ME-001 a ME-013).
- `docs/INSTRUCOES_DEV_FECHAMENTO.md` — instrucoes de execucao do fechamento (historico).

---

## Automacao de Desenvolvimento (Claude Code)

Tudo em `.claude/` (versionado, auto-descoberto) e espelhado como plugin em `devkit/`. Detalhes em `docs/AUTOMACAO.md`.

- **Hooks** (`.claude/settings.json` + `.claude/hooks/`): Pint automatico ao editar `.php`, bloqueio de `.env`, lembrete de `down()` em migrations. Rodam via `docker exec` (nao ha PHP no host).
- **Subagents** (`.claude/agents/`): `laravel-test-writer`, `laravel-module-scaffolder`, `tenancy-security-reviewer` (+ os globais `laravel-senior-architect`, `tech-product-owner`).
- **Skills** (`.claude/skills/`): `padroes-projeto`, `scaffold-modulo`, `gerar-teste-model`, `checklist-pre-pr`.
- **Slash commands** (`.claude/commands/`): `/testar`, `/migrar`, `/auditar-tenancy`, `/pre-pr`.
- Execucao sempre no container: `docker exec meu-negocio-app <cmd>`.

---

## Regras para IA

1. **Sempre buscar padroes visuais** no Duralux Admin antes de criar UI
2. **Sempre perguntar antes** de criar algo grande
3. **Nunca pular etapas**
4. **Validar sempre**: auth, tenant, permissao, plano
5. **Nunca permitir** acesso cruzado entre redes/empresas
6. **Requests unificados**: SalvarXxxRequest
7. **DTOs unificados**: XxxData
8. **Views com partial**: _form.blade.php + $entidade
9. **Busca AJAX**: nunca carregar todos em select HTML
10. **Models**: BaseModel + secoes ASCII art
