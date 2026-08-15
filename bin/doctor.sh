#!/usr/bin/env bash
#
# Diagnostico do ambiente de desenvolvimento e da automacao.
#
#   bash bin/doctor.sh
#
# Existe por um motivo concreto: os hooks liam o stdin com `jq`, que nao vem no
# macOS. Sem ele, cada hook saia calado e a protecao do .env ficou desligada sem
# nenhum sinal. Automacao que falha em silencio e pior que automacao ausente —
# esta e a tela que faz o silencio virar diagnostico.
set -uo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONTAINER="${MEUNEGOCIO_APP_CONTAINER:-meu-negocio-app}"
falhas=0
avisos=0

ok()    { printf '  \033[32m✓\033[0m %s\n' "$*"; }
falha() { printf '  \033[31m✗\033[0m %s\n' "$*"; falhas=$((falhas + 1)); }
aviso() { printf '  \033[33m!\033[0m %s\n' "$*"; avisos=$((avisos + 1)); }
titulo(){ printf '\n\033[1m%s\033[0m\n' "$*"; }

titulo "Hooks (leitura do stdin)"
if command -v jq >/dev/null 2>&1; then
  ok "jq $(jq --version 2>/dev/null) — parser preferido"
elif command -v python3 >/dev/null 2>&1; then
  ok "python3 $(python3 --version 2>&1 | cut -d' ' -f2) — fallback ativo (jq ausente, tudo bem)"
else
  falha "nem jq nem python3: TODOS os hooks estao inertes, inclusive o guard-env (.env desprotegido)"
  echo "      instale um: brew install jq"
fi

# Prova de fogo: o guard-env realmente nega?
if [ -f "$RAIZ/.claude/hooks/guard-env.sh" ]; then
  saida=$(CLAUDE_PROJECT_DIR="$RAIZ" bash "$RAIZ/.claude/hooks/guard-env.sh" <<< '{"tool_input":{"file_path":"'"$RAIZ"'/.env"}}' 2>/dev/null)
  if printf '%s' "$saida" | grep -q '"deny"'; then
    ok "guard-env nega edicao de .env (testado agora, nao presumido)"
  else
    falha "guard-env NAO bloqueou o .env — a protecao esta desligada"
  fi
fi

titulo "Docker (PHP roda no container; nao ha PHP no host)"
if ! command -v docker >/dev/null 2>&1; then
  falha "docker ausente no PATH"
elif docker ps --format '{{.Names}}' 2>/dev/null | grep -qx "$CONTAINER"; then
  ok "container $CONTAINER no ar"
  versao=$(docker exec "$CONTAINER" php -r 'echo PHP_VERSION;' 2>/dev/null)
  [ -n "$versao" ] && ok "PHP $versao no container"
  if docker exec "$CONTAINER" test -f vendor/bin/pint 2>/dev/null; then
    ok "dependencias instaladas (vendor/)"
  else
    falha "vendor/ ausente — rode: docker exec $CONTAINER composer install"
  fi
else
  falha "container $CONTAINER parado — rode: docker compose up -d"
fi

titulo "Automacao (.claude/ -> devkit/)"
if [ -f "$RAIZ/bin/sync-devkit.sh" ]; then
  if bash "$RAIZ/bin/sync-devkit.sh" --check >/dev/null 2>&1; then
    ok "devkit em sincronia com .claude/"
  else
    falha "devkit fora de sincronia — rode: bash bin/sync-devkit.sh (o CI barra o PR assim)"
  fi
fi
printf '  \033[32m✓\033[0m %s rules, %s skills, %s agents, %s commands, %s hooks\n' \
  "$(find "$RAIZ/.claude/rules" -name '*.md' 2>/dev/null | wc -l | tr -d ' ')" \
  "$(find "$RAIZ/.claude/skills" -name 'SKILL.md' 2>/dev/null | wc -l | tr -d ' ')" \
  "$(find "$RAIZ/.claude/agents" -name '*.md' 2>/dev/null | wc -l | tr -d ' ')" \
  "$(find "$RAIZ/.claude/commands" -name '*.md' 2>/dev/null | wc -l | tr -d ' ')" \
  "$(find "$RAIZ/.claude/hooks" -name '*.sh' ! -name 'lib.sh' 2>/dev/null | wc -l | tr -d ' ')"

titulo "Git"
branch=$(git -C "$RAIZ" rev-parse --abbrev-ref HEAD 2>/dev/null)
case "$branch" in
  main|master) aviso "voce esta na $branch — comece o trabalho novo com: git checkout -b tipo/descricao" ;;
  *)           ok "branch de trabalho: $branch" ;;
esac
if [ -n "$(git -C "$RAIZ" status --porcelain 2>/dev/null)" ]; then
  aviso "working tree sujo ($(git -C "$RAIZ" status --porcelain | wc -l | tr -d ' ') arquivos)"
else
  ok "working tree limpo"
fi
mergeadas=$(git -C "$RAIZ" branch -r --merged origin/main 2>/dev/null | grep -v 'origin/main$' | grep -v HEAD | wc -l | tr -d ' ')
if [ "${mergeadas:-0}" -gt 0 ]; then
  aviso "$mergeadas branch(es) remota(s) ja mergeada(s) — limpe com: bash bin/limpar-branches.sh"
else
  ok "sem branches remotas obsoletas"
fi
if command -v gh >/dev/null 2>&1; then
  if gh api repos/{owner}/{repo}/branches/main/protection >/dev/null 2>&1; then
    ok "main protegida no GitHub"
  else
    aviso "main SEM protecao no GitHub (e ela publica em producao) — rode: bash bin/configurar-github.sh"
  fi
fi

titulo "Smoke de tela (opcional)"
if [ -d "$RAIZ/node_modules/puppeteer-core" ] || node -e "require('puppeteer-core')" >/dev/null 2>&1; then
  ok "puppeteer-core disponivel"
else
  aviso "puppeteer-core ausente — o smoke da skill validar-implementacao e pulado (npm i --no-save puppeteer-core)"
fi
if [ -x "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" ] || command -v google-chrome >/dev/null 2>&1; then
  ok "Chrome disponivel"
else
  aviso "Chrome ausente — sem ele nao ha validacao de JS por clique real"
fi

printf '\n\033[1mResumo:\033[0m %s falha(s), %s aviso(s)\n' "$falhas" "$avisos"
[ "$falhas" -gt 0 ] && exit 1
exit 0
