#!/usr/bin/env bash
#
# PostToolUse hook (matcher: Write|Edit)
# Regenera o devkit/ sempre que .claude/ muda. O CI tem um passo que barra o PR
# quando os dois divergem (bin/sync-devkit.sh --check); rodar o sync aqui faz
# esse passo nunca falhar por esquecimento — o erro deixa de ser possivel em vez
# de ser detectado tarde.
#
# So reage ao que o devkit espelha: agents/, skills/, commands/, hooks/ e o
# settings.json (que vira hooks.json). Mexer em rules/ nao dispara nada — elas
# sao conhecimento deste projeto e ficam de fora do plugin.
#
# Nunca bloqueia: qualquer falha sai com 0.
#
# Entrada: JSON do Claude Code via stdin com .tool_input.file_path

source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

entrada=$(cat)
file=$(hook_campo "$entrada" tool_input.file_path)
[ -z "$file" ] && exit 0

rel=$(hook_relativo "$file")

case "$rel" in
  .claude/agents/*|.claude/skills/*|.claude/commands/*|.claude/hooks/*|.claude/settings.json) ;;
  *) exit 0 ;;
esac

raiz="${CLAUDE_PROJECT_DIR:-$(pwd)}"
[ -f "$raiz/bin/sync-devkit.sh" ] || exit 0

if saida=$(cd "$raiz" && bash bin/sync-devkit.sh 2>&1); then
  exit 0   # silencioso no caminho feliz: ruido em todo edit de skill nao ajuda
else
  hook_avisar "sync-devkit falhou — rode 'bash bin/sync-devkit.sh' a mao antes do PR, senao o CI barra. Saida: $(printf '%s' "$saida" | tail -3)"
fi

exit 0
