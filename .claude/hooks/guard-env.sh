#!/usr/bin/env bash
#
# PreToolUse hook (matcher: Write|Edit)
# Bloqueia escrita/edicao de arquivos de ambiente reais (.env, .env.local,
# .env.production, ...). Permite explicitamente o .env.example (template
# versionado). A decisao e comunicada via JSON permissionDecision=deny.
#
# Entrada: JSON do Claude Code via stdin com .tool_input.file_path

input=$(cat)
file=$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty' 2>/dev/null)
[ -z "$file" ] && exit 0

base=$(basename "$file")

case "$base" in
  .env.example)
    exit 0
    ;;
  .env|.env.*)
    jq -n --arg f "$base" '{
      hookSpecificOutput: {
        hookEventName: "PreToolUse",
        permissionDecision: "deny",
        permissionDecisionReason: ("Edicao de \($f) bloqueada pelo hook guard-env. Segredos nao devem ser alterados por aqui; ajuste manualmente ou use .env.example.")
      }
    }'
    exit 0
    ;;
esac

exit 0
