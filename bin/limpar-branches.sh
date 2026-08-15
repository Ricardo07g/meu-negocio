#!/usr/bin/env bash
#
# Remove branches remotas ja mergeadas na main. Seguro por construcao: so toca
# no que o git confirma estar contido em `origin/main` — nenhum commit se perde,
# e a branch pode ser recriada do SHA a qualquer momento.
#
#   bash bin/limpar-branches.sh           # lista e pede confirmacao
#   bash bin/limpar-branches.sh --dry-run # so lista
#   bash bin/limpar-branches.sh --sim     # sem perguntar (uso em script)
#
# Nao substitui o `delete_branch_on_merge` do GitHub (ver bin/configurar-github.sh),
# que evita o acumulo daqui pra frente; este script limpa o passivo.
set -euo pipefail

BASE="${BASE:-main}"
MODO="${1:-}"

git fetch origin --prune >/dev/null 2>&1 || true

mapfile -t mergeadas < <(
  git branch -r --merged "origin/${BASE}" \
    | grep -v "origin/${BASE}\$" \
    | grep -v HEAD \
    | sed 's|origin/||' \
    | tr -d ' '
)

if [ "${#mergeadas[@]}" -eq 0 ]; then
  echo "Nenhuma branch mergeada em origin/${BASE}. Arvore limpa."
  exit 0
fi

echo "Branches ja mergeadas em origin/${BASE} (${#mergeadas[@]}):"
for b in "${mergeadas[@]}"; do
  sha=$(git rev-parse --short "origin/${b}" 2>/dev/null || echo '?')
  printf '  %-45s %s\n' "$b" "$sha"
done

echo
echo "Nao mergeadas (preservadas):"
git branch -r --no-merged "origin/${BASE}" | grep -v HEAD | sed 's/^/  /' || echo "  (nenhuma)"

if [ "$MODO" = "--dry-run" ]; then
  echo
  echo "(--dry-run: nada foi removido)"
  exit 0
fi

if [ "$MODO" != "--sim" ]; then
  echo
  read -r -p "Remover as ${#mergeadas[@]} branches remotas acima? [s/N] " resposta
  case "$resposta" in
    s|S|sim|SIM) ;;
    *) echo "Cancelado."; exit 0 ;;
  esac
fi

for b in "${mergeadas[@]}"; do
  git push origin --delete "$b" >/dev/null 2>&1 && echo "  removida: $b" || echo "  FALHOU:   $b"
done

git fetch origin --prune >/dev/null 2>&1 || true
echo
echo "Pronto. Restantes:"
git branch -r | grep -v HEAD | sed 's/^/  /'
