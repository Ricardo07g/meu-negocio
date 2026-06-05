#!/usr/bin/env bash
#
# sync-devkit.sh — gera o plugin distribuivel (devkit/) a partir da fonte canonica .claude/.
#
# .claude/ e a UNICA fonte de verdade. O devkit/ e derivado e NAO deve ser editado a mao.
# O CI roda `bin/sync-devkit.sh --check` e barra o merge se houver divergencia (drift).
#
# Espelhado:  agents/  skills/  commands/  hooks/*.sh  + hooks/hooks.json (gerado do settings.json)
# NAO espelhado: rules/ (conhecimento de dominio deste projeto), settings.json, agent-memory/ (estado)
#
# Uso:
#   bin/sync-devkit.sh           # regenera o devkit/ a partir do .claude/
#   bin/sync-devkit.sh --check   # nao escreve; sai !=0 se o devkit/ estiver fora de sincronia
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$ROOT/.claude"
DEST="$ROOT/devkit/meu-negocio-devkit"
SETTINGS="$SRC/settings.json"

MODE="sync"
[ "${1:-}" = "--check" ] && MODE="check"

command -v python3 >/dev/null 2>&1 || { echo "ERRO: python3 necessario para gerar hooks.json"; exit 2; }

# Materializa os componentes espelhados dentro de "$1".
gerar_em() {
  local target="$1"
  rm -rf "$target/agents" "$target/skills" "$target/commands" "$target/hooks"
  mkdir -p "$target/hooks"
  cp -R "$SRC/agents"   "$target/agents"
  cp -R "$SRC/skills"   "$target/skills"
  cp -R "$SRC/commands" "$target/commands"
  cp "$SRC"/hooks/*.sh  "$target/hooks/"
  # hooks.json: extrai .hooks do settings.json e troca o prefixo de caminho project -> plugin.
  python3 - "$SETTINGS" "$target/hooks/hooks.json" <<'PY'
import json, sys
src, dst = sys.argv[1], sys.argv[2]
data = json.load(open(src))
txt = json.dumps({"hooks": data["hooks"]}, indent=2, ensure_ascii=False)
txt = txt.replace("${CLAUDE_PROJECT_DIR}/.claude/hooks/", "${CLAUDE_PLUGIN_ROOT}/hooks/")
open(dst, "w").write(txt + "\n")
PY
}

if [ "$MODE" = "check" ]; then
  TMP="$(mktemp -d)"
  trap 'rm -rf "$TMP"' EXIT
  gerar_em "$TMP"
  fail=0
  for d in agents skills commands hooks; do
    if ! diff -ru "$DEST/$d" "$TMP/$d" >/dev/null 2>&1; then
      echo "── DRIFT em devkit/meu-negocio-devkit/$d ──"
      diff -ru "$DEST/$d" "$TMP/$d" || true
      fail=1
    fi
  done
  if [ "$fail" -ne 0 ]; then
    echo
    echo "✗ devkit fora de sincronia com .claude/. Rode: bin/sync-devkit.sh"
    exit 1
  fi
  echo "✓ devkit em sincronia com .claude/"
  exit 0
fi

gerar_em "$DEST"
echo "✓ devkit sincronizado a partir de .claude/"
printf '  agents:   %s\n' "$(find "$DEST/agents" -name '*.md' | wc -l | tr -d ' ')"
printf '  skills:   %s\n' "$(find "$DEST/skills" -name 'SKILL.md' | wc -l | tr -d ' ')"
printf '  commands: %s\n' "$(find "$DEST/commands" -name '*.md' | wc -l | tr -d ' ')"
printf '  hooks:    %s\n' "$(find "$DEST/hooks" -type f | wc -l | tr -d ' ')"
