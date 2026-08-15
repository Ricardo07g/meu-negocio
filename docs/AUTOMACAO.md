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
| `javascript-telas.md` | JS proprio ou inline em Blade |
| `testes-por-feature.md` | modulos e `tests/` — a **regua** do que cada feature exige de teste |
| `modulos/{modulo}.md` (14) | o respectivo `app/Modules/{Modulo}/` |
| `fluxos.md` | fluxos ponta-a-ponta (venda, pagamento, agenda, caixa, estoque) |

> As rules foram **reconciliadas contra o codigo real** (nao copiadas do `.ai/`), pois sao tratadas
> como fonte autoritativa pela IA — acuracia e o objetivo.

## Hooks (qualidade automatica)

Definidos em `.claude/settings.json`. Todos leem o JSON do stdin pela **`hooks/lib.sh`** e **falham de
forma segura** (nunca travam o fluxo). PHP/Pint rodam no container (`docker exec`), pois nao ha PHP no
host.

| Evento | Hook | O que faz |
|---|---|---|
| PreToolUse `Write\|Edit` | `guard-env.sh` | bloqueia edicao de `.env` real (permite `.env.example`) |
| PreToolUse `Write\|Edit` | `guard-devkit.sh` | bloqueia edicao em `devkit/` — e **gerado**; aponta o arquivo de origem em `.claude/` |
| PreToolUse `Bash` | `guard-migration.sh` | lembra de `down()` reversivel ao aplicar migrations |
| PreToolUse `Bash` | `guard-branch.sh` | recusa `git commit`/`push` na **main** (ela publica em producao); avisa em `gh pr merge` |
| PostToolUse `Write\|Edit` | `pint.sh` | formata o `.php` recem-editado |
| PostToolUse `Write\|Edit` | `blade-lint.sh` | confere `.blade.php`: SweetAlert sem fallback, interpolacao em aspas simples, diretivas desbalanceadas |
| PostToolUse `Write\|Edit` | `sync-devkit.sh` | re-gera o `devkit/` quando `.claude/` muda — o passo de drift do CI deixa de falhar por esquecimento |

> **Por que existe a `lib.sh`.** Os hooks liam o stdin com `jq`, que **nao vem instalado no macOS**.
> Sem ele, cada hook caia no `exit 0` e saia calado: o `guard-env` parou de proteger o `.env` sem
> nenhum sinal disso. A lib tenta `jq`, cai para `python3` e, se faltarem os dois, avisa uma vez por
> dia. Falhar de forma segura e certo; falhar de forma **invisivel** nao e — e e o que
> `bin/doctor.sh` e o teste de integridade passaram a vigiar.

## Subagents

- **laravel-test-writer** — testes Feature/Unit no padrao da suite (trait `CriaTenant`, SQLite in-memory) + factories.
- **laravel-module-scaffolder** — esqueleto de modulo (Controller fino, Service/Action, Request/DTO unificados, Policy registrada, BaseModel, Views com `_form`).
- **tenancy-security-reviewer** — revisor read-only de isolamento `rede_id`/`empresa_id`, Policies e `authorize()`.
- **tech-product-owner** — PO tecnico (especifica features, criterios de aceite, trade-offs).

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
- **fluxo-git** — o ciclo completo: partir da main atualizada, nomear a branch, agrupar commits,
  abrir o PR, mergear (= publicar).

## Slash commands

- `/nova-feature <descricao>` — comeca o trabalho a partir da main atualizada, criando a branch no padrao.
- `/testar [filtro]` — roda a suite no container.
- `/migrar` — aplica migrations no container.
- `/auditar-tenancy [escopo]` — dispara o `tenancy-security-reviewer`.
- `/pre-pr` — porta de qualidade (Pint + PHPStan + testes) + `checklist-pre-pr`.

## Code review — quem revisa quem

Num projeto de um desenvolvedor so, tudo e **auto-review**: o mesmo agente que escreveu conferindo o
proprio trabalho, que e exatamente quando ponto cego sobrevive. Tres camadas, e so a ultima e
independente:

| Camada | Quando | Quem revisa |
|---|---|---|
| skill `revisar-codigo` | durante o trabalho, sobre o diff | o proprio agente |
| `/auditar-tenancy` (subagente `tenancy-security-reviewer`) | antes do PR, read-only | subagente com contexto isolado |
| `.github/workflows/code-review.yml` | ao abrir/atualizar o PR | **agente que nao escreveu o codigo** |

O job do CI **comenta, nunca reprova** — quem decide continua sendo quem abriu. Prioriza tenancy, JS
de tela e a regua de `testes-por-feature`, e diz "nada critico encontrado" quando e o caso, em vez de
inventar achado para parecer util. Sem o secret `ANTHROPIC_API_KEY`, o job avisa e sai sem quebrar
nada. PR vindo de fork nao recebe secret (gatilho `pull_request`, nao `pull_request_target`), entao
ali ele simplesmente pula.

## Fluxo git — a main publica em producao

Nao e uma branch de integracao: e o que os usuarios estao rodando. Por isso o ciclo e sempre
`main atualizada -> branch tipo/descricao -> PR -> CI verde -> merge`, e o merge se confirma antes.

- `bash bin/configurar-github.sh` — aplica no GitHub o que o fluxo pressupoe: PR obrigatorio, check
  "Tests + Pint" obrigatorio, force-push bloqueado, branch deletada ao mergear. `--check` so mostra o estado.
- `bash bin/limpar-branches.sh` — remove as branches remotas ja mergeadas (o passivo).
- `bash bin/doctor.sh` — diagnostico: hooks ativos, container no ar, devkit em sincronia, main
  protegida, branches obsoletas, Chrome/puppeteer para o smoke.

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

## Qualidade medida (evals)

A automacao nao e so escrita — e **medida**, e o harness esta versionado em **[`evals/`](../evals/README.md)**
para que a afirmacao seja verificavel, nao declarada. Tres niveis:

| Nivel | Pergunta | Custo | Onde roda |
|---|---|---|---|
| **1 — Integridade** | rules apontam para arquivos que existem? `paths:` casa com algo? hooks funcionam? | zero | **CI**, a cada push |
| **2 — Triggering** | a skill certa dispara — e fica quieta quando nao e o caso? | centavos | sob demanda |
| **3 — A/B** | a skill melhora o resultado vs. o mesmo agente sem ela? | dolares | sob demanda |

```bash
docker exec meu-negocio-app php artisan test --filter=AutomacaoIntegridadeTest   # nivel 1
bash evals/bin/triggering.sh                                                     # nivel 2
```

O nivel 1 (`tests/Feature/AutomacaoIntegridadeTest.php`) e o que mais paga no dia a dia: pega a rule
cujo `paths:` nao casa com nada — ela existe, parece saudavel e **nunca carrega** — e o hook que
depende so de `jq`. O nivel 2 mede a `description`, unico texto da skill sempre em contexto, e conta
falso positivo separado de falso negativo (pedem correcoes opostas). Os cenarios do nivel 3 saem de
defeitos que aconteceram de verdade aqui.

### Iteracao 1 do A/B (6 cenarios reais, modelo Sonnet, 1 run por configuracao)

| Metrica | Com skill | Sem skill |
|---------|-----------|-----------|
| Pass rate | 100% | 90% |
| Tempo medio | ~238s | ~316s (~24% mais lento) |
| Tokens medios | ~59k | ~74k (~20% a mais) |

Leitura: a skill agrega **consistencia** (variancia zero) e **eficiencia** (mais rapida e barata,
pois direciona o agente as dimensoes certas — ex.: `validar-implementacao` sempre cobre o smoke de
tela, `revisar-codigo` usa a taxonomia Critico/Importante/Sugestao). O baseline ja forte (90%)
confirma que o ganho estrutural vem das `rules` lazy + `CLAUDE.md`, que qualquer agente herda neste
repo — a skill poe o acabamento. Harness leve (amostra pequena), entao e sinal de direcao, nao
benchmark estatistico.

## Pre-requisitos

> Confira tudo de uma vez com **`bash bin/doctor.sh`** — ele testa o hook de verdade em vez de
> presumir que esta ativo.

- Docker Compose rodando (container `meu-negocio-app`; override via `MEUNEGOCIO_APP_CONTAINER`).
- `jq` **ou** `python3` no host (hooks; a `lib.sh` usa o que houver). `python3` no host/CI (gera o
  `hooks.json` no `sync-devkit.sh`).
- `claude` no PATH para os evals de nivel 2 e 3 (opcional).
- Smoke da `validar-implementacao`: `google-chrome` + `puppeteer-core` no host (opcional; sem eles, o smoke e pulado e a tela e coberta por teste de view).
