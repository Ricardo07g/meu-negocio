# Meu Negocio — Devkit (plugin Claude Code)

Empacota a automacao de desenvolvimento do projeto **Meu Negocio** como um plugin Claude Code
distribuivel: subagents, skills, hooks e slash commands.

> **Importante:** neste repositorio, a automacao **ativa** e a copia nativa em `.claude/`
> (auto-descoberta, sem instalacao). Este plugin e a forma **portatil** dos mesmos componentes —
> util para instalar em outro checkout/maquina, compartilhar com o time, ou estudar o empacotamento.
> O `.claude/` nativo e canonico; este plugin e **gerado** dele por `bin/sync-devkit.sh` e o CI
> valida que os dois estao em sincronia (ver `docs/AUTOMACAO.md`). Nao edite o `devkit/` a mao.

## Conteudo

- **agents/** — `laravel-test-writer`, `laravel-module-scaffolder`, `tenancy-security-reviewer`, `tech-product-owner`
- **skills/** — `padroes-projeto`, `scaffold-modulo`, `gerar-teste-model`, `checklist-pre-pr`, `validar-implementacao`, `revisar-codigo`, `depurar`, `criar-migration`, `adicionar-permissao`, `documentar-adr`, `escrever-commit`
- **commands/** — `/testar`, `/migrar`, `/auditar-tenancy`, `/pre-pr`
- **hooks/** — Pint pos-edicao de `.php`, guard de `.env`, lembrete de `down()` em migrations

> O conhecimento de dominio do projeto (`.claude/rules/`) **nao** vai no plugin — e especifico do Meu
> Negocio. O devkit distribui apenas a automacao reutilizavel (agents/skills/commands/hooks).

## Instalacao (a partir do marketplace local)

```
/plugin marketplace add ./devkit
/plugin install meu-negocio-devkit@meu-negocio-marketplace
```

Depois disso os comandos ficam namespaced como `/meu-negocio-devkit:testar`, etc., e os agents/skills
aparecem em `/agents` e `/skills`.

## Pre-requisitos

- Docker Compose do projeto rodando (container `meu-negocio-app`). O nome do container pode ser
  sobrescrito via env `MEUNEGOCIO_APP_CONTAINER`.
- `jq` disponivel no host (usado pelos hooks para ler o JSON do stdin).
