# Automacao de Desenvolvimento (Claude Code)

Este projeto usa os mecanismos de automacao do Claude Code para desenvolver com qualidade e padrao
consistente. Tudo vive em `.claude/` (versionado, exceto `settings.local.json`) e e auto-descoberto —
nao precisa instalar nada. O `devkit/` empacota os mesmos componentes como **plugin** distribuivel.

## Visao geral

| Mecanismo | Onde | O que faz |
|-----------|------|-----------|
| **Hooks** | `.claude/settings.json` + `.claude/hooks/*.sh` | Disparam sozinhos no ciclo de vida das ferramentas |
| **Subagents** | `.claude/agents/*.md` | Trabalhadores especializados com contexto isolado |
| **Skills** | `.claude/skills/<nome>/SKILL.md` | Conhecimento/procedimento carregado sob demanda |
| **Slash commands** | `.claude/commands/*.md` | Atalhos de dev-ops acionados pelo usuario |
| **Plugin** | `devkit/meu-negocio-devkit/` | Forma portatil/distribuivel dos itens acima |

## Hooks (qualidade automatica)

Definidos em `.claude/settings.json`; scripts leem o JSON do stdin via `jq` e **falham de forma
segura** (nunca travam o fluxo). PHP/Pint rodam no container Docker (`docker exec`), pois nao ha PHP
no host.

- **PostToolUse `Write|Edit`** → `hooks/pint.sh`: formata o `.php` recem-editado com o Pint.
- **PreToolUse `Write|Edit`** → `hooks/guard-env.sh`: bloqueia edicao de `.env` reais (permite `.env.example`).
- **PreToolUse `Bash`** → `hooks/guard-migration.sh`: lembra de `down()` reversivel ao aplicar migrations.

> Ao mudar hooks, o Claude Code pede sua aprovacao na proxima sessao (seguranca). E esperado.

## Subagents

- **laravel-test-writer** — escreve testes Feature/Unit no padrao da suite (trait `CriaTenant`,
  SQLite in-memory) e gera factories. Execucao via `docker exec`.
- **laravel-module-scaffolder** — gera esqueleto de modulo (Controller fino, Service/Action,
  Request/DTO unificados, Policy registrada, BaseModel, Views com `_form`).
- **tenancy-security-reviewer** — revisor read-only de isolamento `rede_id`/`empresa_id`, Policies e `authorize()`.

Os agentes pre-existentes `tech-product-owner` (projeto) e `laravel-senior-architect` (global) continuam validos.

## Skills

- **padroes-projeto** (reference, auto-invocavel) — blueprints e convencoes; aponta para arquivos
  canonicos reais do repo (`references/blueprints.md`).
- **scaffold-modulo** — procedimento para criar um modulo completo.
- **gerar-teste-model** — gera teste + factory para um Model/fluxo.
- **checklist-pre-pr** — roteiro da porta de qualidade + tenancy + docs + commits.

## Slash commands

- `/testar [filtro]` — roda a suite no container.
- `/migrar` — aplica migrations no container.
- `/auditar-tenancy [escopo]` — dispara o `tenancy-security-reviewer`.
- `/pre-pr` — porta de qualidade (Pint + PHPStan + testes) + `checklist-pre-pr`.

## Como os pecas se compoem

```
/scaffold-modulo  ─usa→  skill scaffold-modulo  ─consulta→  skill padroes-projeto
                                     └─delega→  agente laravel-module-scaffolder
/gerar-teste-model ─usa→ skill gerar-teste-model ─delega→ agente laravel-test-writer
/pre-pr ─executa→ Pint+PHPStan+testes ─e segue→ skill checklist-pre-pr ─chama→ /auditar-tenancy
hooks ─garantem→ Pint automatico, .env protegido, lembrete de down()
```

## Plugin (distribuicao)

`devkit/meu-negocio-devkit/` e um plugin Claude Code completo (`.claude-plugin/plugin.json`,
`agents/`, `skills/`, `commands/`, `hooks/hooks.json`), publicado por um marketplace local em
`devkit/.claude-plugin/marketplace.json`.

```
/plugin marketplace add ./devkit
/plugin install meu-negocio-devkit@meu-negocio-marketplace
```

**Canonico vs plugin:** a copia em `.claude/` e a que esta ativa neste repo (sem instalacao). O plugin
**espelha** esses arquivos para uso portatil (outro checkout, time, ou estudo do empacotamento). Ao
editar a automacao, atualize o `.claude/` nativo e re-espelhe no `devkit/` (cp dos arquivos) para
manter os dois em sincronia. Diferenca tecnica: os hooks do plugin usam `${CLAUDE_PLUGIN_ROOT}`; os
nativos usam `${CLAUDE_PROJECT_DIR}`.

## Pre-requisitos

- Docker Compose rodando (container `meu-negocio-app`; override via `MEUNEGOCIO_APP_CONTAINER`).
- `jq` no host (hooks).
