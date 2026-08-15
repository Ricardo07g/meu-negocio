#!/usr/bin/env bash
#
# Base comum dos hooks. Resolve um problema que passou despercebido por meses:
# todo hook lia o JSON do stdin com `jq`, e `jq` nao vem instalado no macOS.
# Sem ele, cada hook caia no `[ -z "$file" ] && exit 0` e saia calado — o
# guard-env parou de proteger o .env sem que nada indicasse isso. Falhar de
# forma segura e certo; falhar de forma INVISIVEL nao e.
#
# Agora: tenta jq, cai para python3 (presente no macOS e ja exigido pelo
# sync-devkit) e, se nenhum existir, avisa uma vez por dia em vez de sumir.
#
# Uso:
#   source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
#   entrada=$(cat)
#   file=$(hook_campo "$entrada" tool_input.file_path)
#   hook_negar "motivo"      |  hook_avisar "mensagem"

# Extrai um campo pontilhado do JSON. Vazio se ausente ou se nao ha parser.
hook_campo() {
  local json="$1" caminho="$2"

  if command -v jq >/dev/null 2>&1; then
    printf '%s' "$json" | jq -r --arg p "$caminho" 'getpath($p | split(".")) // empty' 2>/dev/null
    return
  fi

  if command -v python3 >/dev/null 2>&1; then
    printf '%s' "$json" | python3 -c '
import json, sys
try:
    dado = json.load(sys.stdin)
except Exception:
    sys.exit(0)
for parte in sys.argv[1].split("."):
    if not isinstance(dado, dict) or parte not in dado:
        sys.exit(0)
    dado = dado[parte]
if dado is not None:
    print(dado)
' "$caminho" 2>/dev/null
    return
  fi

  hook_alerta_sem_parser
}

# Sem jq nem python3 os hooks nao tem como ler a entrada. Avisa no maximo uma
# vez por dia (marcador em /tmp) para nao virar ruido em toda ferramenta usada.
hook_alerta_sem_parser() {
  local marcador="/tmp/.mn-hooks-sem-parser-$(date +%Y%m%d)"
  [ -f "$marcador" ] && return
  touch "$marcador" 2>/dev/null
  printf '%s\n' '{"systemMessage":"Hooks do projeto inativos: nem jq nem python3 encontrados no host. Rode `bash bin/doctor.sh` para o diagnostico."}'
}

# Escapa uma string para caber dentro de um literal JSON.
hook_escapar() {
  if command -v python3 >/dev/null 2>&1; then
    printf '%s' "$1" | python3 -c 'import json,sys; print(json.dumps(sys.stdin.read())[1:-1])' 2>/dev/null && return
  fi
  # Fallback pobre: barra invertida, aspas e quebras de linha.
  printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g' | tr '\n' ' '
}

# Nega a chamada da ferramenta (so vale em PreToolUse).
hook_negar() {
  printf '{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":"%s"}}\n' \
    "$(hook_escapar "$1")"
}

# Mensagem informativa, sem alterar a decisao.
hook_avisar() {
  printf '{"systemMessage":"%s"}\n' "$(hook_escapar "$1")"
}

# Caminho do arquivo relativo a raiz do projeto.
hook_relativo() {
  local file="$1" raiz="${CLAUDE_PROJECT_DIR:-$(pwd)}"
  printf '%s' "${file#"$raiz"/}"
}
