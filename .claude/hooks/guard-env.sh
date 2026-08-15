#!/usr/bin/env bash
#
# PreToolUse hook (matcher: Write|Edit)
# Bloqueia escrita/edicao de arquivos de ambiente reais (.env, .env.local,
# .env.production, ...). Permite explicitamente o .env.example (template
# versionado). A decisao e comunicada via JSON permissionDecision=deny.
#
# Entrada: JSON do Claude Code via stdin com .tool_input.file_path

source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

entrada=$(cat)
file=$(hook_campo "$entrada" tool_input.file_path)
[ -z "$file" ] && exit 0

base=$(basename "$file")

case "$base" in
  .env.example)
    exit 0
    ;;
  .env|.env.*)
    hook_negar "Edicao de ${base} bloqueada pelo hook guard-env. Segredos nao devem ser alterados por aqui; ajuste manualmente ou use .env.example."
    exit 0
    ;;
esac

exit 0
