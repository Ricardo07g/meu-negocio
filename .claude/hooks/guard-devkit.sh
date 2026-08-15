#!/usr/bin/env bash
#
# PreToolUse hook (matcher: Write|Edit)
# Bloqueia edicao direta em devkit/: aquele diretorio e GERADO a partir de
# .claude/ pelo bin/sync-devkit.sh. Editar la e trabalho perdido — o proximo
# sync sobrescreve — e o CI so descobre depois do push (passo "Devkit
# sincronizado com .claude/"). Melhor negar aqui, com o caminho certo na mao.
#
# Excecao: devkit/.claude-plugin/marketplace.json e os README.md sao escritos a
# mao (nao saem do .claude/), entao passam.
#
# Entrada: JSON do Claude Code via stdin com .tool_input.file_path

source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

entrada=$(cat)
file=$(hook_campo "$entrada" tool_input.file_path)
[ -z "$file" ] && exit 0

rel=$(hook_relativo "$file")

case "$rel" in
  devkit/.claude-plugin/marketplace.json) exit 0 ;;
  devkit/*README.md) exit 0 ;;
esac

case "$rel" in
  devkit/*)
    # devkit/meu-negocio-devkit/skills/depurar/SKILL.md -> .claude/skills/depurar/SKILL.md
    origem=$(printf '%s' "$rel" | sed -E 's|^devkit/[^/]+/|.claude/|')
    hook_negar "O devkit/ e gerado, nao editado: ${rel} seria sobrescrito pelo proximo bin/sync-devkit.sh. Edite ${origem} e rode 'bash bin/sync-devkit.sh'."
    exit 0
    ;;
esac

exit 0
