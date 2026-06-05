#!/usr/bin/env bash
#
# PostToolUse hook (matcher: Write|Edit)
# Formata o arquivo PHP recem-editado com o Laravel Pint, executando dentro
# do container Docker (no host nao ha PHP). Nunca bloqueia o fluxo: qualquer
# falha (docker ausente, container parado, arquivo nao-PHP) sai com 0.
#
# Entrada: JSON do Claude Code via stdin com .tool_input.file_path
# Variaveis: CLAUDE_PROJECT_DIR (root do projeto), MEUNEGOCIO_APP_CONTAINER (override)

input=$(cat)
file=$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty' 2>/dev/null)

# Sem arquivo, template Blade, ou nao-PHP: nada a fazer.
[ -z "$file" ] && exit 0
case "$file" in
  *.blade.php) exit 0 ;;
  *.php) ;;
  *) exit 0 ;;
esac

project_dir="${CLAUDE_PROJECT_DIR:-$(pwd)}"
# Caminho relativo ao root do projeto (= /var/www/html dentro do container).
rel="${file#"$project_dir"/}"

container="${MEUNEGOCIO_APP_CONTAINER:-meu-negocio-app}"

if command -v docker >/dev/null 2>&1 \
   && docker ps --format '{{.Names}}' 2>/dev/null | grep -qx "$container"; then
  docker exec "$container" vendor/bin/pint "$rel" >/dev/null 2>&1 || true
fi

exit 0
