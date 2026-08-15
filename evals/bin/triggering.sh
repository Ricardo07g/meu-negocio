#!/usr/bin/env bash
#
# EVAL NIVEL 2 — Triggering
#
# Pergunta: dada a `description` de cada skill, o modelo escolhe a certa para o
# pedido do usuario? E, tao importante quanto: ele resiste a acionar skill quando
# o pedido nao pede nenhuma?
#
# Este e o eval que mais paga: a description e o unico texto da skill que fica
# sempre em contexto, e uma skill que nunca dispara e uma skill que nao existe.
# Nada e executado — so a decisao e medida —, entao roda em segundos e custa
# centavos.
#
#   bash evals/bin/triggering.sh                   # 1 execucao por caso (rapido)
#   bash evals/bin/triggering.sh --runs 3          # 3 execucoes: mede a VARIANCIA
#   bash evals/bin/triggering.sh --caso "branch"   # filtra por substring
#   bash evals/bin/triggering.sh --modelo opus
#
# Sobre `--runs`: a escolha do modelo nao e deterministica. Rodando uma vez por
# caso, dois runs seguidos da MESMA configuracao trocaram de erro — casos que
# falharam num passaram no outro. Com 1 execucao voce corrige ruido achando que
# corrige a description. Um caso instavel (acerta as vezes) e um sinal diferente
# de um caso que erra sempre, e so N execucoes separam os dois.
#
# Requer: `claude` no PATH (autenticado).
set -uo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
CENARIOS="$RAIZ/evals/cenarios/triggering.tsv"
RESULTADOS="$RAIZ/evals/resultados"
MODELO="sonnet"
FILTRO=""
RUNS=1

while [ $# -gt 0 ]; do
  case "$1" in
    --modelo) MODELO="$2"; shift 2 ;;
    --caso)   FILTRO="$2"; shift 2 ;;
    --runs)   RUNS="$2"; shift 2 ;;
    *) echo "opcao desconhecida: $1"; exit 2 ;;
  esac
done

command -v claude >/dev/null 2>&1 || { echo "claude nao encontrado no PATH."; exit 1; }
[ -f "$CENARIOS" ] || { echo "cenarios nao encontrados: $CENARIOS"; exit 1; }

# Catalogo = o que o modelo realmente ve: nome + description de cada skill.
catalogo=$(
  for skill in "$RAIZ"/.claude/skills/*/SKILL.md; do
    nome=$(basename "$(dirname "$skill")")
    desc=$(sed -n 's/^description: *//p' "$skill" | head -1 | tr -d '"')
    printf -- '- %s: %s\n' "$nome" "$desc"
  done
)

mkdir -p "$RESULTADOS"
carimbo=$(date +%Y%m%d-%H%M%S)
saida="$RESULTADOS/triggering-${carimbo}.tsv"
printf 'prompt\tesperado\tacertos\truns\tobtidos\n' > "$saida"

casos=0; tentativas=0; acertos_totais=0
falsos_positivos=0; falsos_negativos=0; instaveis=0

printf '\033[1mEval de triggering\033[0m  (modelo: %s, %s execucao(oes) por caso)\n\n' "$MODELO" "$RUNS"

while IFS=$'\t' read -r prompt esperado; do
  case "$prompt" in ''|'#'*) continue ;; esac
  [ -z "${esperado:-}" ] && continue
  if [ -n "$FILTRO" ] && ! printf '%s' "$prompt" | grep -qi -- "$FILTRO"; then continue; fi

  pergunta="Voce e um agente com estas skills disponiveis:

${catalogo}

O usuario disse: \"${prompt}\"

Qual skill voce invocaria ANTES de responder? Se nenhuma se aplica, responda exatamente: nenhuma
Responda SO com o nome da skill (ou 'nenhuma'), sem explicacao, sem pontuacao."

  acertos_caso=0; obtidos=""
  for _ in $(seq 1 "$RUNS"); do
    obtido=$(printf '%s' "$pergunta" | claude -p --model "$MODELO" 2>/dev/null \
              | tr -d '\r' | tail -1 | tr -d ' .`' | tr '[:upper:]' '[:lower:]')
    [ -z "$obtido" ] && obtido="(sem resposta)"
    obtidos="${obtidos}${obtidos:+,}${obtido}"
    tentativas=$((tentativas + 1))

    if [ "$obtido" = "$esperado" ]; then
      acertos_caso=$((acertos_caso + 1))
      acertos_totais=$((acertos_totais + 1))
    elif [ "$esperado" = "nenhuma" ]; then
      falsos_positivos=$((falsos_positivos + 1))
    elif [ "$obtido" = "nenhuma" ]; then
      falsos_negativos=$((falsos_negativos + 1))
    fi
  done

  casos=$((casos + 1))

  if [ "$acertos_caso" -eq "$RUNS" ]; then
    printf '  \033[32m✓\033[0m %-52.52s %s\n' "$prompt" "$esperado"
  elif [ "$acertos_caso" -eq 0 ]; then
    printf '  \033[31m✗\033[0m %-52.52s esperado=%s obtido=%s\n' "$prompt" "$esperado" "$obtidos"
  else
    # Nem sempre erra, nem sempre acerta: a description discrimina mal, e a
    # instabilidade e o achado — nao um resultado a arredondar.
    instaveis=$((instaveis + 1))
    printf '  \033[33m~\033[0m %-52.52s INSTAVEL %s/%s (%s)\n' "$prompt" "$acertos_caso" "$RUNS" "$obtidos"
  fi

  printf '%s\t%s\t%s\t%s\t%s\n' "$prompt" "$esperado" "$acertos_caso" "$RUNS" "$obtidos" >> "$saida"
done < "$CENARIOS"

[ "$casos" -eq 0 ] && { echo "Nenhum caso executado."; exit 1; }

taxa=$(( acertos_totais * 100 / tentativas ))
printf '\n\033[1mResultado:\033[0m %s/%s decisoes corretas (%s%%) em %s caso(s)\n' \
  "$acertos_totais" "$tentativas" "$taxa" "$casos"
printf '  falso positivo (disparou sem precisar): %s\n' "$falsos_positivos"
printf '  falso negativo (nao disparou quando devia): %s\n' "$falsos_negativos"
[ "$RUNS" -gt 1 ] && printf '  \033[33minstaveis (acertam so as vezes): %s\033[0m\n' "$instaveis"
printf '  registro: %s\n' "${saida#"$RAIZ"/}"

# Piso conservador: abaixo disso alguma description esta ambigua demais.
[ "$taxa" -lt 70 ] && exit 1
exit 0
