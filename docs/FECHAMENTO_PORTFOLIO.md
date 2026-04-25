# Fechamento de Escopo — Meu Negócio (Portfólio)

> Documento técnico de execução para finalizar o projeto como peça de portfólio.
> Audiência: agente dev `laravel-senior-architect`. Linguagem: PT-BR. Formato: backlog priorizado com critérios de aceite.

---

## 1. Sumário Executivo

Estado geral: **maduro funcionalmente, imaturo como entregável open source/portfólio**. O domínio (multi-tenant, Title+Parcela+Baixa, Caixa diário, Venda+Estorno) é robusto, bem modelado e demonstra padrões avançados (Actions/Services/DTOs unificados, _form partials, traits de tenancy). Há, contudo, **inconsistências bloqueantes para uma avaliação técnica externa**: README é o boilerplate genérico do Laravel, `.env.example` está com SQLite/locale `en` (nada roda fora-da-caixa em Docker como anunciado), zero testes além do esqueleto, sem CI, sem LICENSE/CHANGELOG/CONTRIBUTING, e há divergências documentação ↔ código (FullCalendar declarado vs Toast UI Calendar usado, 7 papéis no `PapelEnum` vs 2 no `PermissaoSeeder`, flag de plano `tem_relatorios` sem módulo correspondente).

Esforço relativo total estimado: **~30 pontos de esforço** (S=1, M=3, L=8) — distribuídos em ~22 itens de backlog. P0 representa ~12 pontos e fecha o gap de avaliabilidade; P1 (~12 pontos) é o que diferencia o portfólio; P2 (~6 pontos) é polimento.

Riscos principais: (1) o tempo se evapora na "polidura final" se não houver corte explícito; (2) há tentação de completar `tem_relatorios` — recomenda-se **CORTAR**, não completar; (3) Auth incompleto (sem reset de senha) é o item mais visível para um avaliador.

---

## 2. Estado Real do Projeto vs CLAUDE.md

### 2.1 O que confere com a CLAUDE.md
| Item | Status |
|---|---|
| Stack PHP 8.3 / Laravel 13 / MySQL / Redis / Docker | OK (`composer.json`, `docker-compose.yml`) |
| Pacotes Spatie (permission, data, activitylog) | OK |
| Estrutura modular `app/Modules/{Modulo}` | OK (15 módulos) |
| BaseModel + RedeTrait/EmpresaTrait | OK |
| Idioma PT em tabelas/models/rotas | OK (com pequenas exceções) |
| Modelo Título + Parcela + Baixa | OK (`pagamentos`, `parcelas_pagamento`, `baixas_pagamento` e equivalentes para Despesa) |
| Requests unificados `SalvarXxxRequest` | OK majoritariamente (Cliente, Servico, Produto, Despesa, Empresa, Usuario, Papel) |
| DTOs unificados `XxxData` | OK (Cliente, Servico, Produto, Despesa, etc.) |
| `_form.blade.php` partial | OK em 8 módulos |
| Componentes `<x-form-botoes>`, `<x-show-botoes>`, `<x-label-info>` | OK |
| Caixa diário com navegação prev/next, sangria, reforço, retroativo | OK |
| Multi-tenant single DB com `rede_id` + `empresa_id` | OK |
| Middleware `verificar.rede`, `verificar.empresa`, `verificar.plano` | OK |
| Seeds padrão ao registrar (categorias, produtos, serviços, clientes) | OK em `RedeService::criar()` |
| Seeder de desenvolvimento volumoso com `admin@teste.com / password` | OK (`DesenvolvimentoSeeder`) |

### 2.2 O que diverge ou está superestimado
| Declarado | Realidade | Severidade |
|---|---|---|
| "FullCalendar 6" na CLAUDE.md e `.ai/contexto/stack.md` | `package.json` e `resources/js/calendar.js` usam **`@toast-ui/calendar` ^2.1.3** | Documentação errada (P1) |
| `Papel` parcial na CLAUDE.md | Na verdade está **completo** (Controller, Service, Request, Policy, _form, todas as views), commit `dc04798` confirma | CLAUDE.md desatualizada (P2) |
| "Auth — Login/Registrar" parcial | Confirmado parcial: **falta reset de senha** completamente (rota não existe, view não existe, mailable não existe), apesar da migration criar `password_reset_tokens` | Gap funcional (P1) |
| "Dashboard cards reais" | Cards existem mas o dashboard é **chapado**: sem listas auxiliares (próximos agendamentos, top clientes, alertas de estoque baixo, parcelas vencendo nos próximos 7 dias) | Usabilidade (P1) |
| Plano `tem_relatorios` (boolean) | Flag existe na migration, no model, no `VerificarPlano` e em `PlanoService`, mas **não há módulo Relatórios, nem rota, nem view** que consuma | Funcionalidade fantasma (P0) |
| `PapelEnum` com 7 papéis (Admin, Gerente, Profissional, Recepcao, Financeiro, Estoque, Visualizador) | `PermissaoSeeder` cria **apenas 2 Roles** (Admin, Profissional). Criar usuário com `papel=Gerente` passa validação mas explode no `assignRole` | Bug funcional (P0) |
| "Modelos com seções ASCII art" (Relations, Acessors, Mutators, Scopes, Methods) | Padrão aplicado, mas as seções **estão vazias** em vários models (Empresa, Plano, Rede, Usuario), apenas relations preenchidas | Visual (P2) |
| `RegistraAtividade` em "operações críticas" | Apenas **3 modelos** usam: `Agendamento`, `Pagamento`, `Caixa`. Ausentes: Despesa, VendaPacote, VendaProduto, MovimentoEstoque, Cliente, Usuario, Empresa | Auditoria incompleta (P1) |
| Policies aplicadas | Faltam `VendaProdutoPolicy`, `ParcelaPagamentoPolicy`, `ParcelaDespesaPolicy`, `BaixaPagamentoPolicy`, `BaixaDespesaPolicy`, `MovimentoCaixaPolicy`, `RolePolicy` registrada para Spatie Role mas não para `VendaProduto` no `AppServiceProvider` | Segurança (P1) |
| `.env.example` pronto para Docker | `.env.example` está com `DB_CONNECTION=sqlite`, `APP_LOCALE=en`, `APP_FAKER_LOCALE=en_US` — **divergente do `.env` real** (mysql + pt_BR) | Onboarding quebrado (P0) |

### 2.3 O que está SUBESTIMADO (saldo positivo)
| Achado | Comentário |
|---|---|
| `DesenvolvimentoSeeder` rico (500 clientes, 30 produtos, 600 agendamentos com vendas reais, cancelamentos, baixas) | Excelente para demo. Não está mencionado na CLAUDE.md. |
| Documentação interna `.ai/` | 30+ arquivos cobrindo contexto, fluxos, módulos. Recurso valioso mas oculto. |
| `CalculadoraParcelas` em `app/Support/Parcelamento/` | Componente reutilizável, padrão DDD, isolado e testável. |
| Ações isoladas (`CriarVendaProdutoAction`, `SincronizarItensVendaProdutoAction`, etc.) | Padrão Action bem aplicado. Demonstra Clean Architecture. |
| Estorno automático ao cancelar venda | Implementação não trivial, com regra de negócio bem encapsulada em `CaixaService::estornarPagamento`. |
| ViaCEP auto-fill + máscaras (telefone/CPF/CEP) no layout global | Detalhe de UX que destoa positivamente. |

---

## 3. Definition of Done para Portfólio

Critérios objetivos. **Cada item precisa estar verdadeiro para considerar o projeto entregue.**

### 3.1 Onboarding (clonar e rodar em < 5 min)
- [ ] `.env.example` espelha o `.env` real (MySQL + Redis + pt_BR + APP_URL com porta 8080).
- [ ] `README.md` substitui o boilerplate Laravel e contém: pitch (3 linhas), screenshots, stack, pré-requisitos, passos exatos para subir via Docker, credencial do seeder demo, link para `.ai/` e `docs/`.
- [ ] Sequência `git clone → cp .env.example .env → docker compose up -d → composer setup → php artisan db:seed --class=DesenvolvimentoSeeder` resulta em sistema funcional acessível em http://localhost:8080.
- [ ] `LICENSE` (MIT) presente na raiz.

### 3.2 Coerência funcional
- [ ] Não existe funcionalidade declarada (em flag de plano, enum, ou seeder) sem ponto de uso real OU está explicitamente cortada.
- [ ] Login + Registro + Logout + Reset de Senha (mínimo via log driver) funcionam ponta a ponta.
- [ ] Criar usuário com qualquer `papel` do `PapelEnum` resulta em sucesso (Role correspondente existe no seeder).
- [ ] Todos os controllers chamam `authorize()` ou usam Request com `authorize()` que verifica `can()`.

### 3.3 Coerência arquitetural
- [ ] Não há classes/DTOs/métodos públicos órfãos (sem caller).
- [ ] Documentação interna (CLAUDE.md, `.ai/contexto/stack.md`) bate com o `composer.json`/`package.json` real.
- [ ] Padrão `_form.blade.php` aplicado a todos os módulos com formulário (Usuario, Agenda também — hoje só 8 módulos têm).
- [ ] Modelos críticos (Pagamento, Despesa, Venda*, Caixa, MovimentoEstoque) usam `RegistraAtividade` para auditoria.

### 3.4 Demonstrabilidade técnica
- [ ] Ao menos **8 testes Feature** cobrindo os fluxos-chave: registro de rede, login, criar venda à vista (com baixa de caixa), criar venda a prazo (gera parcelas pendentes), baixa de parcela, estorno via cancelamento, abertura/fechamento de caixa, isolamento multi-tenant (usuário rede A não vê dados rede B).
- [ ] Testes rodam via `composer test` (já configurado em `composer.json`) e passam em SQLite in-memory.
- [ ] CI básico (GitHub Actions) executa `composer test` + `vendor/bin/pint --test` em push/PR.
- [ ] `.github/PULL_REQUEST_TEMPLATE.md` (opcional, baixa prioridade).

### 3.5 Polimento de UI/UX
- [ ] Zero `alert()` raw no JS (substituir os 3 ocorrências em `Venda/Views/create.blade.php` e `edit-produto.blade.php` por `swalAlerta`).
- [ ] Sem TODOs visíveis em produção (TODO "Meu Perfil" no header — decidir entre IMPLEMENTAR ou REMOVER).
- [ ] Dashboard com pelo menos uma seção de "lista" (próximos agendamentos OU parcelas a vencer) além dos cards.

---

## 4. Backlog de Fechamento — Priorizado

> **Convenção:** S = ~1h, M = ~3-5h, L = 1+ dia. Esforço é relativo ao agente dev no padrão estabelecido.

---

### FECH-001 — Reescrever README.md como peça de portfólio
- **Tipo:** DOCUMENTAR
- **Módulo afetado:** raiz
- **Descrição:** O `README.md` atual é o template padrão do Laravel. Para portfólio, é o **primeiro e mais importante artefato**. Deve responder em < 30 segundos: o que é, para quem, como rodar, screenshots, stack, decisões de arquitetura.
- **Critério de aceite:**
  - Seções obrigatórias: (1) Pitch curto + badge stack, (2) Screenshots (mínimo 3: dashboard, agenda, venda em andamento), (3) Stack & decisões de arquitetura (linkando `.ai/contexto/stack.md`), (4) Setup Docker passo a passo, (5) Credenciais demo (`admin@teste.com / password`), (6) Estrutura de pastas (apenas alto nível), (7) Roadmap (o que está fora de escopo deste portfólio), (8) Licença.
  - Em PT-BR (pode ter um README-en.md complementar — não obrigatório).
  - Sem dependência do template Laravel original.
- **Esforço:** M
- **Prioridade:** P0
- **Instruções técnicas:**
  1. Capturar 3-5 screenshots das telas-chave após rodar `DesenvolvimentoSeeder`. Salvar em `docs/screenshots/`.
  2. Substituir o conteúdo de `/README.md`. Manter referência ao Laravel apenas na seção "Built with".
  3. Documentar a sequência exata de comandos para subir o projeto. Testar em `git clone` limpo se possível.
  4. Linkar para `.ai/README.md` na seção de arquitetura.

---

### FECH-002 — Corrigir `.env.example` para refletir setup Docker real
- **Tipo:** CORRIGIR / DOCUMENTAR
- **Módulo afetado:** raiz
- **Descrição:** Quem clonar o repo e copiar `.env.example → .env` recebe SQLite + locale `en`. Inconsistente com `docker-compose.yml` (MySQL) e com `.env` real (pt_BR). Onboarding quebrado.
- **Critério de aceite:**
  - `.env.example` contém: `DB_CONNECTION=mysql`, `DB_HOST=mysql`, `DB_PORT=3306`, `DB_DATABASE=meu_negocio`, `DB_USERNAME=meu_negocio`, `DB_PASSWORD=secret`, `REDIS_HOST=redis`, `APP_LOCALE=pt_BR`, `APP_FALLBACK_LOCALE=pt_BR`, `APP_FAKER_LOCALE=pt_BR`, `APP_URL=http://localhost:8080`, `APP_NAME="Meu Negócio"`.
  - **NÃO** incluir o `APP_KEY` real do `.env` privado.
  - Manter chaves AWS/Mail vazias (são opcionais).
- **Esforço:** S
- **Prioridade:** P0
- **Instruções técnicas:**
  1. Editar `/.env.example` com base no `/.env` atual, removendo a `APP_KEY`.
  2. Validar que não há segredos vazando (senha de produção, tokens).

---

### FECH-003 — Sincronizar `PermissaoSeeder` com `PapelEnum`
- **Tipo:** CORRIGIR
- **Módulo afetado:** `database/seeders/PermissaoSeeder.php`, `app/Modules/Usuario`
- **Descrição:** `PapelEnum` declara 7 papéis (Admin, Gerente, Profissional, Recepcao, Financeiro, Estoque, Visualizador). `PermissaoSeeder` cria apenas Admin e Profissional. `SalvarUsuarioRequest` valida com `Rule::enum(PapelEnum::class)` — então o usuário pode escolher "Gerente" no form, passa a validação, e o `Spatie\Permission` falha ao atribuir Role inexistente.
- **Critério de aceite:**
  - **OPÇÃO ESCOLHIDA — RECOMENDADA: simplificar enum.** Manter apenas papéis que são reais no projeto: `Admin`, `Profissional`, `Recepcao`, `Financeiro`. Remover Gerente, Estoque, Visualizador do `PapelEnum`. `PermissaoSeeder` cria os 4 papéis com permissões coerentes.
  - Profissional: `agendamento.ver/criar`, `cliente.ver`, `servico.ver`, `agenda.editar` (próprios).
  - Recepcao: tudo de Profissional + `cliente.criar/editar`, `pagamento.ver/criar`.
  - Financeiro: `pagamento.*`, `despesa.*`, `categoria_despesa.*`, `financeiro.*`.
  - Admin: tudo (já está correto).
  - Documentar a matriz de permissões no `.ai/contexto/permissoes-e-papeis.md` (verificar se já bate).
- **Esforço:** M
- **Prioridade:** P0
- **Instruções técnicas:**
  1. Editar `app/Enums/PapelEnum.php` para conter apenas Admin/Profissional/Recepcao/Financeiro.
  2. Editar `database/seeders/PermissaoSeeder.php` adicionando Roles Recepcao e Financeiro com permissões coerentes.
  3. Rodar `php artisan migrate:fresh --seed` e `php artisan db:seed --class=DesenvolvimentoSeeder` para validar.
  4. Confirmar criação de usuário em cada papel via UI (`/usuarios/novo`).
  5. Atualizar `.ai/contexto/permissoes-e-papeis.md` se necessário.

---

### FECH-004 — Cortar `tem_relatorios` ou implementar Relatórios mínimo
- **Tipo:** CORTAR (recomendado) ou COMPLEMENTAR
- **Módulo afetado:** `app/Modules/Tenant`, `app/Http/Middleware/VerificarPlano.php`, `database/seeders/PlanoSeeder.php`
- **Descrição:** A flag `tem_relatorios` existe em `planos` table, em `Plano` model, em `PlanoService`, em `ValidarPlanoAction`, em `VerificarPlano` middleware — **mas nenhuma rota a usa**. É funcionalidade fantasma. Em portfólio, isso parece descuido.
- **Critério de aceite (CORTAR — recomendado):**
  - Migration nova: remover coluna `tem_relatorios` de `planos`.
  - Remover de: `Plano::$fillable`, `Plano::$casts`, `PlanoSeeder`, `PlanoService::verificarLimite`, `ValidarPlanoAction::executar`, `VerificarPlano::handle`, e qualquer view que mencione (não há).
  - Documentar em `.ai/contexto/planos-e-limites.md` (atualizar matriz de planos).
- **Critério de aceite alternativo (COMPLEMENTAR — só se sobrar tempo, NÃO recomendado):**
  - Criar módulo `Relatorios` com 1 relatório útil (ex.: receita por período, ou top 10 clientes por faturamento).
  - Não vale a pena: estimaria L (8 pontos) para algo decorativo.
- **Esforço:** S (cortar) / L (implementar)
- **Prioridade:** P0
- **Instruções técnicas (cortar):**
  1. Criar migration `2026_04_25_000000_remove_tem_relatorios_from_planos.php` (drop column).
  2. Atualizar 5 arquivos PHP listados acima.
  3. Atualizar `.ai/contexto/planos-e-limites.md`.

---

### FECH-005 — Implementar Reset de Senha (mínimo viável)
- **Tipo:** COMPLEMENTAR
- **Módulo afetado:** `app/Modules/Auth`
- **Descrição:** A migration cria tabela `password_reset_tokens`, mas não há controller, rota nem view. Em uma aplicação SaaS, ausência de "esqueci minha senha" é o tipo de coisa que avaliador técnico nota imediatamente.
- **Critério de aceite:**
  - Rotas: `GET /esqueci-senha`, `POST /esqueci-senha`, `GET /redefinir-senha/{token}`, `POST /redefinir-senha`.
  - Controllers: `EsqueciSenhaController` (formulário + envio do email), `RedefinirSenhaController` (formulário + atualização).
  - Mailable `RecuperacaoSenhaMailable` (markdown) — funciona com `MAIL_MAILER=log` (suficiente para portfólio).
  - Link "Esqueci minha senha" na tela de login.
  - Validações: email existe, token não expirado (60 minutos), nova senha mínimo 8 caracteres + confirmação.
  - Rate limit (`throttle:5,1` na rota `POST /esqueci-senha`).
  - Mensagem genérica no formulário (não vazar se email existe — boa prática de segurança).
- **Esforço:** M
- **Prioridade:** P0
- **Instruções técnicas:**
  1. Usar Laravel Password Broker (`Password::sendResetLink`, `Password::reset`) — não reinventar.
  2. Criar `EsqueciSenhaController` em `app/Modules/Auth/Controllers/`.
  3. Views: `app/Modules/Auth/Views/esqueci-senha.blade.php`, `redefinir-senha.blade.php` — seguir padrão do `auth::login`.
  4. Mailable em `app/Modules/Auth/Mail/RecuperacaoSenhaMailable.php`.
  5. Adicionar testes Feature (ver FECH-014).

---

### FECH-006 — Cobrir Auth e fluxos críticos com testes Feature
- **Tipo:** TESTAR
- **Módulo afetado:** `tests/Feature/`
- **Descrição:** Hoje só existe `ExampleTest`. Para portfólio profissional, cobertura zero é red flag. Não precisa ser cobertura ampla — precisa demonstrar **conhecimento de testing patterns**.
- **Critério de aceite:**
  - Testes em `tests/Feature/` agrupados por contexto (`Auth/`, `Venda/`, `Pagamento/`, `Caixa/`, `MultiTenant/`).
  - Mínimo 8 testes (cada um pode ter 2-3 asserts):
    1. `Auth/RegistroTest::usuario_cria_conta_e_loga()` — registra rede + empresa + usuário admin, redireciona pro dashboard.
    2. `Auth/LoginTest::credenciais_invalidas_falham()`.
    3. `Auth/LoginTest::usuario_inativo_nao_loga()`.
    4. `Venda/VendaAVistaTest::cria_pagamento_e_baixa_caixa()` — caixa aberto, venda à vista de produto, valida que parcela ficou paga e movimento de caixa foi criado.
    5. `Venda/VendaAPrazoTest::gera_parcelas_pendentes()` — 3 parcelas, todas pendentes.
    6. `Pagamento/BaixaParcelaTest::baixa_parcial_atualiza_status()`.
    7. `Caixa/EstornoTest::cancelar_venda_estorna_caixa()`.
    8. `MultiTenant/IsolamentoTest::usuario_rede_a_nao_ve_clientes_rede_b()` — teste central, valida que `RedeTrait` funciona.
  - Usar `RefreshDatabase` (já configurado SQLite in-memory).
  - Factories: criar mínimo `RedeFactory`, `EmpresaFactory`, `UsuarioFactory` em `database/factories/`.
  - `composer test` passa.
- **Esforço:** L
- **Prioridade:** P0
- **Instruções técnicas:**
  1. Criar factories básicas reutilizando lógica do `RedeService::criar()`.
  2. Criar helper de teste `criarRedeAutenticada()` em `tests/TestCase.php` (ou trait).
  3. Cada teste segue padrão AAA (Arrange / Act / Assert).
  4. Não mockar banco. Usar SQLite in-memory.
  5. **Não tentar testar UI/JS** — apenas HTTP + asserts em DB.

---

### FECH-007 — Adicionar CI mínimo (GitHub Actions)
- **Tipo:** DOCUMENTAR / TESTAR
- **Módulo afetado:** raiz (`.github/workflows/`)
- **Descrição:** Para portfólio open source, CI badge no README é sinal de profissionalismo. Mínimo: rodar testes em push.
- **Critério de aceite:**
  - `.github/workflows/ci.yml` executa em push/PR para main.
  - Steps: checkout → setup PHP 8.3 → composer install → cp .env.example .env → key:generate → migrate (sqlite) → composer test → vendor/bin/pint --test.
  - Badge no README.md.
- **Esforço:** S
- **Prioridade:** P0
- **Instruções técnicas:**
  1. Usar `shivammathur/setup-php@v2`.
  2. Cache do Composer.
  3. Não rodar Node/npm a menos que adicione teste de frontend (não há).

---

### FECH-008 — Adicionar `LICENSE` (MIT)
- **Tipo:** DOCUMENTAR
- **Módulo afetado:** raiz
- **Descrição:** Open source sem LICENSE é tecnicamente "all rights reserved". Adicionar arquivo MIT padrão.
- **Critério de aceite:**
  - Arquivo `LICENSE` na raiz com MIT padrão e ano + nome (Ricardo).
- **Esforço:** S
- **Prioridade:** P0
- **Instruções técnicas:**
  1. Copiar texto MIT padrão. Substituir ano (2026) e copyright holder.

---

### FECH-009 — Corrigir documentação: FullCalendar → Toast UI Calendar
- **Tipo:** DOCUMENTAR
- **Módulo afetado:** `CLAUDE.md`, `.ai/contexto/stack.md`, `.ai/modulos/agenda.md` (se aplicável)
- **Descrição:** Documentação afirma FullCalendar 6, código usa `@toast-ui/calendar` 2.x. Inconsistência fácil de corrigir mas sinaliza falta de atenção.
- **Critério de aceite:**
  - `CLAUDE.md`, `.ai/contexto/stack.md` e qualquer doc do módulo Agenda referenciam corretamente Toast UI Calendar.
  - Versão exata vinda do `package.json`.
- **Esforço:** S
- **Prioridade:** P0
- **Instruções técnicas:**
  1. Buscar (`grep -r "FullCalendar"` ) em `.ai/`, `CLAUDE.md`, `INSTRUCTIONS/` e substituir.

---

### FECH-010 — Padronizar autorização em todos os controllers
- **Tipo:** CORRIGIR
- **Módulo afetado:** `app/Modules/Pagamento/Controllers/PagamentoController.php`, `Despesa`, `Caixa`, requests sem `authorize()` real
- **Descrição:** Vários métodos de operações sensíveis chamam apenas `Request::authorize() => true` (sem `can()`) e o controller não chama `$this->authorize()`. Ex.: `PagamentoController::baixaParcela`, `renegociarParcela`, `cancelarParcela`. Em multi-tenant, RouteModelBinding aplica `RedeTrait` (segurança de tenancy OK), mas a permissão (`pagamento.editar`) não é verificada.
- **Critério de aceite:**
  - Todo método de controller que MUTA estado deve chamar `$this->authorize($acao, $modelo)` ou usar Request com `authorize()` que verifique `$this->user()->can(...)`.
  - Padrão a seguir: `SalvarUsuarioRequest` (já correto). Usar como referência.
  - Auditoria: rodar `grep -rn "authorize(): bool" app/Modules/*/Requests/` — para cada `return true;` encontrado, decidir se faz sentido ou se deve verificar `can()`.
- **Esforço:** M
- **Prioridade:** P1
- **Instruções técnicas:**
  1. Listar requests com `authorize() => true` (saber quais).
  2. Para cada um, identificar a permissão correta e implementar `return $this->user()->can('xxx');`.
  3. Para os controllers Pagamento/Despesa/Caixa que não chamam `$this->authorize()`, adicionar nas rotas mutativas.
  4. Validar com teste de isolamento (FECH-006/MultiTenant) e adicionar 1 teste extra: usuário Profissional não consegue dar baixa em parcela de pagamento.

---

### FECH-011 — Substituir `alert()` raw por SweetAlert
- **Tipo:** CORRIGIR
- **Módulo afetado:** `app/Modules/Venda/Views/create.blade.php` (linha 1008), `edit-produto.blade.php` (linhas 188 e 198)
- **Descrição:** 3 ocorrências de `alert()` JS bruto que destoam do padrão SweetAlert estabelecido. Já existe `window.swalAlerta()` global no layout.
- **Critério de aceite:**
  - Zero `alert()` raw em qualquer view do projeto.
  - Substituições usam `swalAlerta(motivo)`.
- **Esforço:** S
- **Prioridade:** P1
- **Instruções técnicas:**
  1. Buscar `grep -rn "alert(" app/Modules/*/Views/`.
  2. Substituir cada ocorrência por `swalAlerta('mensagem')`.

---

### FECH-012 — Implementar perfil do usuário OU remover TODO
- **Tipo:** COMPLEMENTAR ou CORTAR
- **Módulo afetado:** `app/Modules/Usuario`, `resources/views/layouts/app.blade.php` (linha 334)
- **Descrição:** Há um comentário `{{-- TODO: Meu Perfil --}}` no menu de usuário do header. Avaliador externo nota TODO em produção.
- **Critério de aceite (RECOMENDADO — implementar mínimo):**
  - Rota `GET /perfil` → tela com nome, email, papel, empresa, último login (se trackeado).
  - Rota `POST /perfil` → atualizar nome e email.
  - Rota `POST /perfil/senha` → alterar senha (requer senha atual).
  - Link no menu do header substituindo o TODO.
  - Permissão: usuário só edita os próprios dados (sem precisar de Policy se a action é "self").
- **Critério de aceite (alternativa — cortar):**
  - Remover o comentário TODO. Manter apenas o link "Sair".
- **Esforço:** M (implementar) / S (cortar)
- **Prioridade:** P1
- **Instruções técnicas (implementar):**
  1. `PerfilController` em `app/Modules/Usuario/Controllers/PerfilController.php`.
  2. Reusar `SalvarUsuarioRequest` ou criar `AtualizarPerfilRequest` específico.
  3. View `app/Modules/Usuario/Views/perfil.blade.php` reutilizando `_form` se possível.

---

### FECH-013 — Adicionar `RegistraAtividade` em modelos críticos faltantes
- **Tipo:** COMPLEMENTAR
- **Módulo afetado:** `app/Modules/Despesa/Models/Despesa.php`, `app/Modules/Venda/Models/VendaPacote.php`, `VendaProduto.php`, `app/Modules/Estoque/Models/MovimentoEstoque.php`
- **Descrição:** A trait `RegistraAtividade` (Spatie ActivityLog) é usada apenas em Agendamento, Pagamento e Caixa. Para auditoria coerente, modelos transacionais críticos (Despesa, VendaPacote, VendaProduto, MovimentoEstoque) devem ser auditados também.
- **Critério de aceite:**
  - 4 modelos passam a usar `RegistraAtividade`.
  - `LogOptions::defaults()->logAll()->logOnlyDirty()->dontSubmitEmptyLogs()` (já é o padrão da trait).
  - Após uma operação manual em cada (criar despesa, cancelar venda, criar movimento de estoque), entrada aparece em `activity_log` table.
- **Esforço:** S
- **Prioridade:** P1
- **Instruções técnicas:**
  1. Adicionar `use App\Traits\RegistraAtividade;` e `use RegistraAtividade;` no statement de traits do model.
  2. Validar com `php artisan tinker` ou via UI.

---

### FECH-014 — Padronizar `_form.blade.php` em Usuario e Agenda
- **Tipo:** CORRIGIR
- **Módulo afetado:** `app/Modules/Usuario/Views/`, `app/Modules/Agenda/Views/`
- **Descrição:** A CLAUDE.md estabelece "_form partial compartilhado (create/edit)" como padrão. Em 8 módulos está aplicado, mas Usuario tem `create.blade.php` + `edit.blade.php` separados, e Agenda só tem `edit.blade.php` (sem create — agendamento é criado via modal "criar rápido"). Inconsistência visível.
- **Critério de aceite:**
  - Usuario: criar `_form.blade.php` reutilizado por `create` e `edit`. Padrão idêntico ao Cliente/Produto.
  - Agenda: avaliar se faz sentido ter `_form` (provavelmente sim, para `edit`). Decisão: extrair para `_form` e fazer `edit.blade.php` incluí-lo.
  - Manter funcionalidade idêntica. Apenas refator visual.
- **Esforço:** M
- **Prioridade:** P1
- **Instruções técnicas:**
  1. Modelo de referência: `app/Modules/Cliente/Views/_form.blade.php` + `create.blade.php` + `edit.blade.php`.
  2. Manter `@php $entidade = $entidade ?? null; @endphp` no topo do `_form`.
  3. Não criar form de "create" agenda se a regra é só modal — apenas refatorar o edit.

---

### FECH-015 — Enriquecer Dashboard com lista útil
- **Tipo:** COMPLEMENTAR
- **Módulo afetado:** `app/Modules/Dashboard/Services/DashboardService.php`, `Views/dashboard.blade.php`
- **Descrição:** Dashboard tem só cards numéricos. Para portfólio, vale uma seção com listas: próximos 5 agendamentos do dia, top 5 parcelas vencendo nos próximos 7 dias, alertas de estoque baixo (produto com `quantidade <= 5`).
- **Critério de aceite:**
  - 1 seção adicional após os cards atuais com no mínimo "Próximos Agendamentos de Hoje" (5 itens) e "Parcelas Vencendo (7 dias)" (5 itens).
  - Cada linha tem link clicável para o registro.
  - Layout em 2 colunas usando classes `col-xxl-6 col-md-12`.
  - Se vazio, exibir empty state amigável.
- **Esforço:** M
- **Prioridade:** P1
- **Instruções técnicas:**
  1. Adicionar métodos em `DashboardService`: `proximosAgendamentos()`, `parcelasVencendo($dias = 7)`.
  2. Atualizar `indicadores()` para incluir essas chaves.
  3. Atualizar `dashboard.blade.php` adicionando a seção. Usar `table table-hover` (padrão do projeto).

---

### FECH-016 — Remover código morto do módulo Tenant
- **Tipo:** CORTAR
- **Módulo afetado:** `app/Modules/Tenant/`
- **Descrição:** `RedeService::atualizar()` e `RedeService::alterarPlano()` existem, `AtualizarRedeData` DTO existe, `RedePolicy` e `PlanoPolicy` registradas no `AppServiceProvider`, mas nenhuma rota chama essas funcionalidades. É código órfão.
- **Critério de aceite:**
  - **OPÇÃO A (cortar — recomendada):** remover `RedeService::atualizar`, `alterarPlano`, `AtualizarRedeData`, `RedePolicy`, `PlanoPolicy` do registro no AppServiceProvider. Manter apenas o que é usado.
  - **OPÇÃO B (completar):** criar tela "Configurações da Rede" para o Admin gerenciar nome da rede + trocar de plano. Mais esforço, valor incerto para portfólio.
  - Recomendação: cortar.
- **Esforço:** S (cortar) / M (completar)
- **Prioridade:** P2
- **Instruções técnicas (cortar):**
  1. Remover métodos não usados de `RedeService.php`.
  2. Remover `AtualizarRedeData.php`.
  3. Remover registros de `RedePolicy` e `PlanoPolicy` no `AppServiceProvider::$policies`.
  4. Remover policy files se não usados em nenhum `authorize()`.

---

### FECH-017 — Preencher seções ASCII art vazias dos models
- **Tipo:** DOCUMENTAR
- **Módulo afetado:** todos os models que têm seções vazias (Empresa, Plano, Rede, Usuario)
- **Descrição:** Padrão do projeto: cada model tem seções ASCII art (Relations, Acessors, Mutators, Scopes, Methods). Em vários models as seções existem vazias. Para um avaliador, "padrão definido mas não cumprido" pesa pior que "padrão não definido".
- **Critério de aceite:**
  - **OPÇÃO A:** preencher seções não usadas com placeholder mínimo OU remover seção vazia.
  - **OPÇÃO B (mais limpa):** manter seção apenas se há conteúdo nela. Excluir o ASCII de seções vazias.
  - Decisão: revisar caso a caso. Não há ganho funcional, é cosmético.
- **Esforço:** S
- **Prioridade:** P2
- **Instruções técnicas:**
  1. Listar models com seções vazias.
  2. Decidir: remover seções vazias OU preencher com algo útil (ex.: scope `ativo()` em Empresa).

---

### FECH-018 — Indexação e foreign keys faltantes
- **Tipo:** CORRIGIR
- **Módulo afetado:** `app/Modules/Venda/Migrations/`, `app/Modules/Estoque/Migrations/`
- **Descrição:** Algumas migrations faltam `cascadeOnDelete()` em FKs onde faria sentido (ex.: `vendas_produto.usuario_id` sem cascade — se usuário for excluído, venda fica órfã). Em `venda_produto_itens` falta soft deletes (mas pode ser proposital — itens são imutáveis). Avaliar.
- **Critério de aceite:**
  - Auditar todas as migrations de FKs e definir comportamento explícito (`cascadeOnDelete`, `nullOnDelete`, ou restrict).
  - Documentar decisões no commit.
- **Esforço:** M
- **Prioridade:** P2
- **Instruções técnicas:**
  1. Não criar migrations alterando estrutura existente para projeto de portfólio (é destrutivo). Apenas garantir que NOVAS migrations seguem o padrão.
  2. Documentar em `.ai/` as decisões de cascade (quem cascateia, quem null, quem restrict).
  3. Se migration ainda não foi rodada em "produção" (qual produção? — é portfólio), pode editar a migration original. **VALIDAR COM RICARDO** antes.

---

### FECH-019 — Adicionar rate limit em login e registro
- **Tipo:** CORRIGIR
- **Módulo afetado:** `app/Modules/Auth/Controllers/LoginController.php`, `routes/web.php`
- **Descrição:** Login não tem rate limit visível. Em portfólio, código de auth é dos mais inspecionados. Aplicar `throttle:5,1` (5 tentativas por minuto).
- **Critério de aceite:**
  - Rotas `POST /login` e `POST /registrar` com middleware `throttle:5,1`.
  - Após 5 falhas, retornar 429 com mensagem amigável.
- **Esforço:** S
- **Prioridade:** P2
- **Instruções técnicas:**
  1. Adicionar middleware na declaração de rota em `routes/web.php`.

---

### FECH-020 — Criar `CONTRIBUTING.md` mínimo
- **Tipo:** DOCUMENTAR
- **Módulo afetado:** raiz
- **Descrição:** Para projeto open source, sinaliza onboarding de contribuidor. Pode ser curto (10-20 linhas).
- **Critério de aceite:**
  - `CONTRIBUTING.md` com: como rodar localmente (link pro README), padrão de commits (formato observado nos `git log`: `feat:`, `fix:`, `refactor(modulo):`), padrão de branch, como rodar testes, como rodar pint.
- **Esforço:** S
- **Prioridade:** P2
- **Instruções técnicas:**
  1. Olhar `git log --oneline | head -20` para extrair convenção real.

---

### FECH-021 — Documentar decisões de arquitetura em `docs/ADR/`
- **Tipo:** DOCUMENTAR
- **Módulo afetado:** `docs/ADR/`
- **Descrição:** ADRs (Architecture Decision Records) curtos demonstram pensamento técnico. Para portfólio, 3-5 ADRs cobrindo decisões marcantes seria diferencial.
- **Critério de aceite:**
  - 3 a 5 ADRs em `docs/ADR/000X-titulo.md` no formato MADR ou similar (Contexto / Decisão / Consequências).
  - Sugestões:
    1. ADR-0001: Multi-tenant single DB com `rede_id` (vs schema-per-tenant vs database-per-tenant).
    2. ADR-0002: Modelo Título + Parcela + Baixa (vs Pagamento simples).
    3. ADR-0003: Estrutura modular `app/Modules/` (vs estrutura padrão Laravel).
    4. ADR-0004: BaseModel + Traits para tenancy (vs Trait independente, vs middleware per-query).
    5. ADR-0005: Caixa diário com retroativo permitido (vs caixa restrito a "hoje").
- **Esforço:** L
- **Prioridade:** P2
- **Instruções técnicas:**
  1. Formato MADR-light: 1 página cada.
  2. Linkar do README na seção arquitetura.

---

### FECH-022 — Atualizar CLAUDE.md com estado real pós-fechamento
- **Tipo:** DOCUMENTAR
- **Módulo afetado:** `CLAUDE.md`
- **Descrição:** Após executar os itens acima, a CLAUDE.md fica desatualizada (ex.: "Auth parcial" deixa de ser verdade, contagens de módulos completos mudam, papéis mudam).
- **Critério de aceite:**
  - CLAUDE.md reflete o estado final.
  - Seção "Módulos parciais" zerada ou justificada.
  - Stack atualizada (Toast UI vs FullCalendar — depende do FECH-009).
- **Esforço:** S
- **Prioridade:** P0 (último, depois de tudo)
- **Instruções técnicas:**
  1. Executar PRÓXIMO ao fim, depois dos demais itens P0/P1.

---

## 5. Itens explicitamente CORTADOS do escopo

Não fazer NENHUM desses itens, salvo decisão explícita do Ricardo:

| Item | Justificativa |
|---|---|
| Módulo de Relatórios completo (BI, gráficos, exportação) | Esforço L+ para algo decorativo. Cortar `tem_relatorios` (FECH-004) é decisão consciente. |
| 2FA / autenticação multi-fator | Para portfólio é over-engineering. Reset de senha (FECH-005) já cobre o gap visível. |
| Verificação de email no registro | Mesmo motivo do 2FA. Adicionar nota no README "intencionalmente fora de escopo". |
| WebSockets / atualizações em tempo real (ex.: agenda colaborativa) | Complexidade muito alta. Não é diferencial para o tipo de SaaS. |
| Internacionalização real (i18n) com múltiplos idiomas | Projeto é PT-BR by design. Não vale o ROI. |
| API REST/GraphQL para mobile | Não há frontend mobile. Adicionar API expõe superfície de teste sem ganho de portfólio. |
| Pagamento integrado com gateway (Stripe, MercadoPago, Asaas) | É o tipo de coisa que parece feature mas exige conta sandbox, env vars secretas, etc. Para portfólio: documentar como roadmap, **não implementar**. |
| Backup automatizado / DR / observability avançada (Sentry, NewRelic) | Configurar para projeto de portfólio é overhead operacional sem retorno. Documentar como "considerações de produção" no README. |
| Migrations corretivas para mudar comportamento de cascade em tabelas existentes | Risco de quebrar a base de dev. Apenas validar para futuras migrations (FECH-018). |
| Refator para CQRS / Event Sourcing | Não há necessidade de domínio. Padrão atual (Service + Action) já é Clean Architecture suficiente. |
| Criar testes Unit puros (sem DB) para todos os Services | Testes Feature em SQLite cobrem 80% do valor com 30% do esforço. FECH-006 é suficiente. |
| Criar tela de gerenciamento de Plano (admin do SaaS) | É funcionalidade de "super-admin" — mesmo nicho, esforço alto, valor de portfólio baixo. |
| Refatorar `VendaService::listar()` (590 linhas no service) | Funciona. Refatorar tem risco. Pode comentar no ADR como "trade-off conhecido". |

---

## 6. Ordem de Execução Sugerida

### Fase 1 — Onboarding + Coerência Crítica (P0, ~1.5 dia)
1. **FECH-002** (S) — `.env.example` correto.
2. **FECH-009** (S) — Documentação FullCalendar→ToastUI.
3. **FECH-008** (S) — LICENSE.
4. **FECH-003** (M) — Sincronizar PapelEnum + Seeder.
5. **FECH-004** (S) — Cortar `tem_relatorios`.
6. **FECH-005** (M) — Reset de senha.
7. **FECH-001** (M) — README portfólio.

### Fase 2 — Demonstrabilidade técnica (P0/P1, ~1.5 dia)
8. **FECH-006** (L) — Testes Feature.
9. **FECH-007** (S) — CI GitHub Actions.
10. **FECH-010** (M) — Padronizar autorização.
11. **FECH-013** (S) — RegistraAtividade nos demais models.

### Fase 3 — Polimento UI/UX (P1, ~1 dia)
12. **FECH-011** (S) — Substituir alert() por SweetAlert.
13. **FECH-014** (M) — _form em Usuario e Agenda.
14. **FECH-015** (M) — Enriquecer Dashboard.
15. **FECH-012** (M) — Perfil de Usuário (ou cortar TODO).

### Fase 4 — Qualidade e código morto (P2, ~0.5 dia)
16. **FECH-016** (S) — Remover código morto Tenant.
17. **FECH-017** (S) — ASCII art coerente.
18. **FECH-019** (S) — Rate limit auth.

### Fase 5 — Documentação OSS (P2, ~1 dia)
19. **FECH-020** (S) — CONTRIBUTING.md.
20. **FECH-021** (L) — ADRs.
21. **FECH-018** (M) — Auditoria de FKs (ou apenas registrar).

### Fase 6 — Closure
22. **FECH-022** (S) — Atualizar CLAUDE.md com estado final.

### Dependências cruzadas
- FECH-006 (testes) depende de FECH-002 (.env.example correto não afeta SQLite, mas alinhamento ajuda).
- FECH-022 (atualizar CLAUDE.md) depende de TUDO.
- FECH-007 (CI) depende de FECH-006 (precisa de testes pra rodar).
- FECH-001 (README) idealmente depois de FECH-002, FECH-005, FECH-008.

---

## 7. Riscos e Pontos de Atenção

### 7.1 Validar com Ricardo ANTES de executar
- **FECH-003 (papéis)**: a decisão de simplificar para 4 papéis precisa de ok do Ricardo. Pode preferir manter os 7 e completar o seeder. Recomendação técnica: simplificar.
- **FECH-004 (cortar `tem_relatorios`)**: confirmar que o corte é a escolha. Se preferir implementar mínimo, ajustar.
- **FECH-012 (Meu Perfil)**: implementar ou apenas remover TODO?
- **FECH-018 (FKs com cascade em migrations existentes)**: nunca alterar migration já rodada sem confirmação. Apenas auditar.

### 7.2 Riscos técnicos
- **Testes em SQLite vs MySQL**: alguns recursos (ex.: `JSON_TABLE`, `WITH RECURSIVE`) podem se comportar diferente. Hoje o projeto não usa, mas se adicionar, atenção. Documentar limitação.
- **`@toast-ui/calendar` vs Bootstrap**: a integração visual pode ter quirks. Validar antes de mexer no FECH-009 (apenas docs, não código).
- **Spatie/permission cache**: ao alterar roles/permissions, rodar `php artisan permission:cache-reset` ou usar `app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions()`. Documentar no FECH-003.
- **DesenvolvimentoSeeder volumoso (33KB)**: tomar cuidado para não quebrar ao mudar PapelEnum. Validar.

### 7.3 Riscos de processo
- Tentação de adicionar coisas durante a execução. **REGRA**: qualquer item fora deste backlog precisa de aprovação explícita do Ricardo.
- Dev pode querer "refatorar enquanto está mexendo". Limitar refator ao que está escrito no critério de aceite. Outros refactors → criar item novo, não infiltrar.

---

## 8. Handoff para o Agente Dev (`laravel-senior-architect`)

### Regras de execução

1. **Respeitar 100% os padrões da CLAUDE.md.** Não introduzir convenções novas. Não usar inglês onde o padrão é PT.
2. **Não introduzir dependências novas sem aprovar.** O `composer.json` está congelado. Se precisar de pacote, criar item de backlog e validar com Ricardo.
3. **Commit pequeno por item.** Cada FECH-XXX vira 1 PR ou 1 commit (se não há remote PR). Mensagem segue o padrão observado:
   - `feat({modulo}): {descricao curta}` — para complementos
   - `fix({modulo}): {descricao}` — para correções
   - `refactor({modulo}): {descricao}` — para reorganização
   - `docs: {descricao}` — para mudanças apenas em md
   - `test: {descricao}` — para testes
   - `chore: {descricao}` — para CI, env, etc.
   - Referenciar o ID: `feat(auth): adiciona reset de senha (FECH-005)`.
4. **Rodar testes/lint após cada item:**
   - `composer test`
   - `vendor/bin/pint --test`
   - Se quebrar, parar e investigar.
5. **Não executar P1 antes de fechar P0.** Não executar P2 antes de fechar P1. Excecão: itens P2 cosméticos (FECH-017) podem ser feitos junto de itens P1 se incidentais.
6. **Atualizar CLAUDE.md (FECH-022) é o ÚLTIMO.** Antes disso, marcar itens concluídos no próprio `FECHAMENTO_PORTFOLIO.md` (checkbox em frente do título de cada item — opcional, decisão do dev).
7. **Para cada item DOCUMENTAR / DECIDIR:** trazer ao Ricardo as opções e esperar input. Não decidir sozinho mudanças com impacto de produto (ex.: cortar `tem_relatorios` — decisão produto).
8. **Sempre buscar padrões visuais no Duralux Admin** antes de criar UI nova (FECH-005, FECH-012, FECH-015).
9. **Validar multi-tenant em qualquer mudança que toque modelos:** garantir que `RedeTrait` e `EmpresaTrait` continuam aplicados, e que testes de isolamento (FECH-006) passam.
10. **Nunca commitar `.env` real.** O `.gitignore` cobre, mas atenção redobrada no FECH-002.

### Antes de começar
- Ler CLAUDE.md.
- Ler `.ai/README.md` para mapear documentação interna.
- Conferir que `php artisan migrate:fresh --seed && php artisan db:seed --class=DesenvolvimentoSeeder` roda em sucesso.
- Validar acesso a `http://localhost:8080` com `admin@teste.com / password`.

### Ao terminar cada fase
- Reportar ao Ricardo o que foi feito + o que está pendente.
- Antes da Fase 2, ter Fase 1 100% verde no CI.
- Antes do FECH-022, revisar visualmente o sistema clicando pelas telas principais.

### Ao terminar tudo
- Auto-revisar o README em modo "primeiro contato".
- Pedir code review do Ricardo.
- Marcar release `v1.0.0-portfolio` (sugerido).

---

**Fim do documento.**
Mantenedor: Tech Product Owner.
Data: 2026-04-25.
Status: Aprovado para execução pela Fase 1.
