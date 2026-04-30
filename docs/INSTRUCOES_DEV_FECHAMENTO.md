# Instruções para o Agente Dev — Fechamento do Projeto (Multi-fase)

> Documento mestre. Ponto de partida para o agente `laravel-senior-architect` executar todas as refatorações pendentes do projeto `meu-negocio` de forma autônoma e sequencial.

---

## Resumo da missão

Finalizar o projeto como peça de portfólio. Há **2 backlogs** consolidados:

1. **`docs/FECHAMENTO_PORTFOLIO.md`** — backlog original do PO. Fases 2 a 5 e FECH-022 ainda pendentes (Fase 1 já foi executada — commits `dc24ef5` a `982c146` + correção `5ef2fd3`).
2. **`docs/FASE_1_5_MULTI_EMPRESA.md`** — fase nova (multi-empresa N:N). 13 itens (ME-001 a ME-013).

## Ordem de execução obrigatória

Executar **na ordem exata abaixo**. Não pular fases.

1. **Fase 1.5 — Multi-empresa** (`docs/FASE_1_5_MULTI_EMPRESA.md`)
   - Itens ME-001 a ME-013, na ordem definida no documento.
   - **Checkpoint obrigatório:** ao concluir ME-005 (sync pivot), **parar e reportar** antes de iniciar ME-006 (`EmpresaTrait` com sessão — alto risco).
2. **Fase 2 — Demonstrabilidade técnica** (FECH-006, FECH-007, FECH-010, FECH-013)
3. **Fase 3 — Polimento UI/UX** (FECH-011, FECH-014, FECH-015, FECH-012)
4. **Fase 4 — Qualidade e código morto** (FECH-016, FECH-017, FECH-019)
5. **Fase 5 — Documentação OSS** (FECH-020, FECH-021, FECH-018)
6. **Closure** (FECH-022 — atualizar CLAUDE.md com estado final)

Após cada fase completa, **parar e reportar** o estado consolidado antes de iniciar a próxima.

## Decisões de produto consolidadas (não reinventar)

- **Papel/PerfilAcesso:** `PapelEnum` foi eliminado. Validação dinâmica via `exists:roles,name`. `PermissaoSeeder` cria apenas `Admin` master. Demais perfis são criados via UI pelo Admin. Slugs internos de Permission seguem `papel.*` (preservado para não quebrar Roles).
- **Multi-empresa (Fase 1.5):**
  - Cliente, Serviço, Produto = catálogo da rede (sem `empresa_id`).
  - Usuário ↔ Empresa = N:N via pivot `empresa_usuario`.
  - `usuarios.empresa_id` **mantido** como "empresa default ao logar".
  - Admin acessa todas as empresas automaticamente.
  - Não-admin precisa ter ≥1 empresa no pivot (validação obriga).
  - Header tem multi-select com checkboxes ("Empresas: 3 selecionadas ▼").
  - Caixa Diário exige **exatamente 1** empresa selecionada para operar.
- **`tem_relatorios`** foi cortado. Relatórios = roadmap futuro.
- **`atende`** (não `eh_atendente`) é o nome do campo de atendente no `Usuario` (já existia).
- **Reset de senha** já implementado, sem migration nova.

## Regras de execução obrigatórias

1. **1 commit por item** (FECH-XXX ou ME-XXX). Mensagem no padrão observado: `feat({modulo}): ...` / `fix(...)` / `refactor(...)` / `chore: ...` / `docs: ...` / `test: ...`. Sempre referenciar o ID no corpo. **Nunca amendar.**
2. **Após cada item:** rodar `composer test` e `vendor/bin/pint --test`. Baseline conhecida: 1 falha em `Tests\Feature\ExampleTest` (pré-existente). **Não regredir além disso.**
3. **Pint:** corrigir só nos arquivos que tocar.
4. **Não introduzir dependências novas** (composer/npm) sem perguntar ao Ricardo.
5. **Respeitar 100% padrões da `CLAUDE.md`:** PT-BR, BaseModel, RedeTrait/EmpresaTrait, Requests unificados (`SalvarXxxRequest`), DTOs unificados, `_form.blade.php` com `@php $entidade = $entidade ?? null; @endphp`, `<x-form-botoes>`, padrão Duralux Bootstrap 5, SweetAlert2, Feather icons.
6. **Sempre buscar padrões visuais no Duralux Admin** (`/home/ricardo/Documentos/Projetos/TEMAS/Duralux-admin-1.0.0/`) antes de criar UI nova.
7. **Não tocar em itens fora do backlog** sem aprovação.
8. **Limpar cache do Spatie** após mexer em Roles/Permissions: `php artisan permission:cache-reset` ou `forgetCachedPermissions()`.
9. **Validar multi-tenant** após qualquer mudança que toque modelos: `RedeTrait`/`EmpresaTrait` continuam aplicados.
10. **Não amendar `CLAUDE.md` integralmente até FECH-022** — apenas correções incidentais durante a execução.
11. **Não rodar PHP/composer/pint diretamente no host.** Usar `docker compose exec app <comando>` (PHP roda só dentro do container).

## Checkpoints obrigatórios (parar e perguntar)

Em qualquer destes pontos, **PAUSAR e reportar ao Ricardo** antes de seguir:

- Antes de começar **ME-006** (`EmpresaTrait` ler de sessão — risco alto de regressão multi-tenant).
- Ao concluir **toda a Fase 1.5**, antes de iniciar Fase 2.
- Ao concluir **Fase 2**, antes de iniciar Fase 3. E assim por diante.
- Se encontrar **qualquer ambiguidade** entre os documentos e o código real (durante exploração inicial). Não improvisar em decisões de produto.
- Se algum item exigir **escolha entre opções** que não está clara no backlog (ex.: "implementar X completo OU cortar Y" — perguntar).

## Verificação ao concluir cada fase

```bash
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan db:seed --class=DesenvolvimentoSeeder
docker compose exec app php artisan permission:cache-reset
docker compose exec app composer test
docker compose exec app vendor/bin/pint --test
```

Validar manual (checklists de verificação dentro de cada fase nos respectivos documentos).

## Reporte ao final de cada fase

Ao concluir uma fase, reportar:
- Lista de itens com hash do commit.
- Output de `composer test` e `pint` no estado final.
- Decisões tomadas no caminho.
- Bloqueios ou pendências identificadas.
- Próxima fase aguardando aprovação para começar.

## Antes de começar (sempre)

1. Ler `CLAUDE.md` na raiz.
2. Ler `docs/FECHAMENTO_PORTFOLIO.md` (entender o contexto histórico das Fases 1 → 5).
3. Ler `docs/FASE_1_5_MULTI_EMPRESA.md` (fase atual prioritária).
4. Confirmar que `docker compose ps` mostra todos os serviços up.
5. Confirmar que `git status` está limpo.
6. Iniciar pelo primeiro item da Fase 1.5 (ME-001).

---

**Última atualização:** 2026-04-25.
**Maintainer:** Tech Product Owner.
**Executor esperado:** `laravel-senior-architect`.
