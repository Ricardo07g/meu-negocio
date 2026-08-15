#!/usr/bin/env bash
#
# PostToolUse hook (matcher: Write|Edit)
# Lint dos arquivos .blade.php — o ponto cego da porta de qualidade deste repo:
# o Pint pula Blade (hooks/pint.sh), o PHPStan nao le template, e a suite testa
# HTTP, nao o JS que roda no navegador. Foi por essa fresta que passou um botao
# que abria o modal e nao submetia nada.
#
# Checa tres coisas, todas com historico de terem quebrado aqui:
#
#   1. `result.isConfirmed` sem fallback — o SweetAlert2 embarcado no Duralux e
#      anterior a essa propriedade e resolve com `{value: true}`. Confiar so no
#      campo novo faz o botao virar decoracao, sem erro no console.
#   2. `'{{ ... }}'` dentro de aspas simples de JS — escapa as aspas do texto
#      como &quot; e quebra o script inteiro se houver apostrofo. Use @json().
#   3. Diretivas Blade desbalanceadas (@if/@endif e companhia).
#
# Avisa, nunca bloqueia: PostToolUse nao nega, e falso positivo em lint de
# template e comum demais para travar o fluxo. O mesmo conjunto de regras roda
# na suite (tests/Feature/BladeLintTest.php), que e quem barra o PR.
#
# Entrada: JSON do Claude Code via stdin com .tool_input.file_path

source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

entrada=$(cat)
file=$(hook_campo "$entrada" tool_input.file_path)
[ -z "$file" ] && exit 0
case "$file" in *.blade.php) ;; *) exit 0 ;; esac
[ -f "$file" ] || exit 0

avisos=""
add() { avisos="${avisos}${avisos:+ | }$1"; }

# 1. isConfirmed sem fallback para .value no mesmo arquivo
if grep -q 'isConfirmed' "$file" && ! grep -q '\.value' "$file"; then
  linha=$(grep -n 'isConfirmed' "$file" | head -1 | cut -d: -f1)
  add "linha ${linha}: usa 'isConfirmed', que NAO existe no SweetAlert2 do Duralux — o botao abre o modal e nao submete. Use 'result.value' (padrao do repo) ou aceite as duas formas"
fi

# 2. Interpolacao Blade dentro de aspas simples de JS
if grep -Eq "'\{\{[^}]*\}\}'" "$file"; then
  linha=$(grep -nE "'\{\{[^}]*\}\}'" "$file" | head -1 | cut -d: -f1)
  add "linha ${linha}: interpolacao Blade dentro de aspas simples em JS — exibe &quot; no lugar das aspas e quebra o script se o texto tiver apostrofo. Prefira @json(...)"
fi

# 3. Diretivas desbalanceadas (contagem de abre/fecha)
for par in "if:endif" "foreach:endforeach" "forelse:endforelse" \
           "push:endpush" "can:endcan" "unless:endunless" "isset:endisset" "auth:endauth"; do
  abre="${par%%:*}"; fecha="${par##*:}"
  n_abre=$(grep -cE "@${abre}[[:space:]]*\(" "$file" || true)
  n_fecha=$(grep -cE "@${fecha}\b" "$file" || true)
  if [ "$n_abre" -ne "$n_fecha" ]; then
    add "@${abre} x @${fecha} desbalanceados (${n_abre} vs ${n_fecha}) — confira antes de abrir a tela"
  fi
done

[ -z "$avisos" ] && exit 0

hook_avisar "blade-lint em $(basename "$file") — ${avisos}"

exit 0
