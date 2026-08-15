#!/usr/bin/env bash
#
# PreToolUse hook (matcher: Bash)
# Duas travas em torno da main, que aqui NAO e uma branch qualquer: merge na
# main publica em producao (Railway, deploy por git).
#
#   1. BLOQUEIA `git commit` e `git push` quando o HEAD e a main. Trabalho novo
#      comeca numa branch — e o hook diz qual comando roda para sair dessa.
#   2. AVISA (nao bloqueia) em `gh pr merge`: e o momento em que o codigo vai ao ar.
#
# A protecao equivalente no servidor esta em bin/configurar-github.sh; este hook
# e a versao local, que erra cedo em vez de errar no push.
#
# Entrada: JSON do Claude Code via stdin com .tool_input.command

source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

entrada=$(cat)
cmd=$(hook_campo "$entrada" tool_input.command)
[ -z "$cmd" ] && exit 0

raiz="${CLAUDE_PROJECT_DIR:-$(pwd)}"
branch=$(cd "$raiz" && git rev-parse --abbrev-ref HEAD 2>/dev/null) || exit 0

# --- 2. Aviso no merge de PR (publica em producao) ---
if printf '%s' "$cmd" | grep -Eq 'gh +pr +merge'; then
  hook_avisar "Atencao (guard-branch): mergear na main dispara o deploy em producao. Confirme que o CI do PR esta verde antes."
  exit 0
fi

# --- 1. Bloqueio de escrita direta na main ---
case "$branch" in
  main|master) ;;
  *) exit 0 ;;
esac

# `git push` de OUTRA branch estando na main e legitimo (ex.: push origin feat/x).
# So barra o push que levaria a propria main.
acao=""
if printf '%s' "$cmd" | grep -Eq 'git +commit'; then
  acao="commit"
elif printf '%s' "$cmd" | grep -Eq 'git +push' \
     && ! printf '%s' "$cmd" | grep -Eq 'git +push [^|;&]*(feat|fix|chore|docs|refactor|perf|test)/'; then
  acao="push"
fi

[ -z "$acao" ] && exit 0

hook_negar "git ${acao} direto na ${branch} bloqueado pelo hook guard-branch: a ${branch} publica em producao. Crie a branch primeiro — 'git checkout -b tipo/descricao-curta' — e abra PR. Em emergencia real, rode o comando fora do agente."

exit 0
