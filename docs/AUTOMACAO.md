# Automacao de Desenvolvimento (Claude Code)

Este projeto usa os mecanismos do Claude Code para desenvolver com qualidade e padrao consistente —
e e, ele proprio, uma demonstracao de "desenvolvimento assistido por IA com porta de qualidade".
Tudo vive em `.claude/` (versionado, exceto `settings.local.json`) e e auto-descoberto: nao precisa
instalar nada. O `devkit/` empacota os mesmos componentes como **plugin** distribuivel, **gerado** a
partir do `.claude/` (sem copia manual).

## Principio: contexto enxuto, conhecimento lazy

O que sempre carrega e minimo; o detalhe entra sob demanda.

| Camada | Onde | Quando carrega |
|--------|------|----------------|
| **CLAUDE.md** | raiz | sempre (indice enxuto, < 200 linhas) |
| **Rules** (path-scoped) | `.claude/rules/*.md` | **so** ao editar arquivos que casam com o `paths:` da regra |
| **Skills** | `.claude/skills/<nome>/SKILL.md` | so quando a skill e invocada (a descricao fica visivel; o corpo carrega sob demanda) |
| **Subagents** | `.claude/agents/*.md` | so ao serem acionados (contexto isolado) |
| **Hooks** | `.claude/settings.json` + `.claude/hooks/*.sh` | deterministico, no ciclo de vida das ferramentas |

## Rules (conhecimento de dominio, lazy)

Cada arquivo declara um `paths:` no frontmatter e so e injetado quando voce mexe num arquivo do
escopo — mantendo o contexto barato. Substituem a antiga pasta `.ai/`, que era documentacao "morta"
(o assistente nao a lia) e havia desatualizado.

| Regra | Carrega ao editar |
|-------|-------------------|
| `multi-tenant-seguranca.md` | modulos, models, traits, middleware, support, migrations |
| `modelo-financeiro.md` | Pagamento, Despesa, Venda, Caixa, parcelamento |
| `ui-duralux.md` | Blade/Views |
| `banco-de-dados.md` | migrations, `database/` |
| `modulos/{modulo}.md` (14) | o respectivo `app/Modules/{Modulo}/` |
| `fluxos.md` | fluxos ponta-a-ponta (venda, pagamento, agenda, caixa, estoque) |

> As rules foram **reconciliadas contra o codigo real** (nao copiadas do `.ai/`), pois sao tratadas
> como fonte autoritativa pela IA — acuracia e o objetivo.

## Hooks (qualidade automatica)

Definidos em `.claude/settings.json`; scripts leem o JSON do stdin via `jq` e **falham de forma
segura** (nunca travam o fluxo). PHP/Pint rodam no container (`docker exec`), pois nao ha PHP no host.

- **PostToolUse `Write|Edit`** -> `hooks/pint.sh`: formata o `.php` recem-editado com o Pint.
- **PreToolUse `Write|Edit`** -> `hooks/guard-env.sh`: bloqueia edicao de `.env` real (permite `.env.example`).
- **PreToolUse `Bash`** -> `hooks/guard-migration.sh`: lembra de `down()` reversivel ao aplicar migrations.

## Subagents

- **laravel-test-writer** — testes Feature/Unit no padrao da suite (trait `CriaTenant`, SQLite in-memory) + factories.
- **laravel-module-scaffolder** — esqueleto de modulo (Controller fino, Service/Action, Request/DTO unificados, Policy registrada, BaseModel, Views com `_form`).
- **tenancy-security-reviewer** — revisor read-only de isolamento `rede_id`/`empresa_id`, Policies e `authorize()`.
- **tech-product-owner** — PO tecnico (especifica features, criterios de aceite, trade-offs); tem memoria de projeto em `.claude/agent-memory/`.

O agente global `laravel-senior-architect` (fora do repo) tambem e usado para revisao arquitetural.

## Skills

Procedimentos e conhecimento reutilizavel, carregados sob demanda. Refino/criacao validados com o
fluxo de evals do skill-creator (ver `docs/` da automacao).

- **padroes-projeto** — blueprints e convencoes de codigo (aponta para `references/blueprints.md`).
- **scaffold-modulo** — criar um modulo completo no padrao.
- **gerar-teste-model** — teste + factory para um Model/fluxo.
- **checklist-pre-pr** — roteiro completo da porta de qualidade + tenancy + docs + commits.
- **validar-implementacao** — valida uma feature recem-feita ponta-a-ponta (testes do modulo + Pint + PHPStan + smoke headless da tela).
- **revisar-codigo** — auto-review (qualidade, SOLID, padroes, tenancy) por severidade.
- **depurar** — depuracao sistematica (reproduzir -> isolar -> hipotese -> corrigir na raiz + teste).
- **criar-migration** — migration no padrao (tenant cols, FKs por convencao, `down()` reversivel).
- **adicionar-permissao** — permissao/perfil spatie no padrao `recurso.acao` + Policy registrada.
- **documentar-adr** — ADR no padrao `docs/ADR/`.
- **escrever-commit** — mensagem `tipo(modulo): ...`.

## Slash commands

- `/testar [filtro]` — roda a suite no container.
- `/migrar` — aplica migrations no container.
- `/auditar-tenancy [escopo]` — dispara o `tenancy-security-reviewer`.
- `/pre-pr` — porta de qualidade (Pint + PHPStan + testes) + `checklist-pre-pr`.

## Como as pecas se compoem

```
editar app/Modules/Pagamento/...  ─ativa→  rules: modelo-financeiro + multi-tenant-seguranca
/scaffold-modulo   ─usa→ skill scaffold-modulo ─consulta→ padroes-projeto ─delega→ laravel-module-scaffolder
implementar feature ─e dai→ skill validar-implementacao (testes+pint+phpstan+smoke) ─se falha→ skill depurar
/pre-pr ─executa→ Pint+PHPStan+testes ─e segue→ checklist-pre-pr ─chama→ /auditar-tenancy
hooks ─garantem→ Pint automatico, .env protegido, lembrete de down()
```

## Plugin (distribuicao) — fonte unica, sem drift

`devkit/meu-negocio-devkit/` e um plugin Claude Code completo (`.claude-plugin/plugin.json`,
`agents/`, `skills/`, `commands/`, `hooks/hooks.json`), publicado por um marketplace local em
`devkit/.claude-plugin/marketplace.json`.

```
/plugin marketplace add ./devkit
/plugin install meu-negocio-devkit@meu-negocio-marketplace
```

**Fonte canonica:** `.claude/`. O `devkit/` e **gerado** dele — nunca edite o `devkit/` a mao.

```bash
bin/sync-devkit.sh          # regenera o devkit/ a partir do .claude/
bin/sync-devkit.sh --check  # nao escreve; falha se houver divergencia (drift)
```

O passo **`Devkit sincronizado com .claude/`** do CI roda `--check` e barra o PR se alguem editou um
lado sem re-sincronizar. O que e espelhado: `agents/`, `skills/`, `commands/`, `hooks/*.sh` e o
`hooks/hooks.json` (gerado do `settings.json`, trocando `${CLAUDE_PROJECT_DIR}/.claude/hooks/` por
`${CLAUDE_PLUGIN_ROOT}/hooks/`). **Nao** espelhados: `rules/` (conhecimento especifico deste projeto),
`settings.json` e `agent-memory/` (estado de runtime).

## Pre-requisitos

- Docker Compose rodando (container `meu-negocio-app`; override via `MEUNEGOCIO_APP_CONTAINER`).
- `jq` no host (hooks). `python3` no host/CI (gera o `hooks.json` no `sync-devkit.sh`).
- Smoke da `validar-implementacao`: `google-chrome` + `puppeteer-core` no host (opcional; sem eles, o smoke e pulado e a tela e coberta por teste de view).
