#!/usr/bin/env bash
#
# PreToolUse hook (matcher: Bash)
# Lembrete NAO-BLOQUEANTE: ao aplicar uma migration (php artisan migrate),
# relembra de garantir um metodo down() reversivel. Nao altera a decisao de
# permissao — apenas emite um systemMessage. Ignora migrate:status/rollback/
# fresh/refresh/reset/install e execucoes com --pretend.
#
# Entrada: JSON do Claude Code via stdin com .tool_input.command

input=$(cat)
cmd=$(printf '%s' "$input" | jq -r '.tool_input.command // empty' 2>/dev/null)
[ -z "$cmd" ] && exit 0

if printf '%s' "$cmd" | grep -Eq 'artisan +migrate( |$|;|&)' \
   && ! printf '%s' "$cmd" | grep -Eq 'migrate:(status|rollback|fresh|refresh|reset|install)' \
   && ! printf '%s' "$cmd" | grep -Eq -- '--pretend'; then
  jq -n '{ systemMessage: "Lembrete (guard-migration): confirme que a migration possui down() reversivel antes de aplicar." }'
fi

exit 0
