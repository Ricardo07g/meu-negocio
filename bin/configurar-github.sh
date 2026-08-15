#!/usr/bin/env bash
#
# Aplica no GitHub as regras de fluxo que este repo assume (ver CONTRIBUTING.md).
# Idempotente: rode quantas vezes quiser.
#
#   bash bin/configurar-github.sh            # aplica
#   bash bin/configurar-github.sh --check    # so mostra o estado atual, nao escreve
#
# Por que isto existe: `main` publica em producao (Railway, deploy por git) e o
# repo e publico. Sem protecao, um `git push` distraido — humano ou agente — vai
# direto para producao sem passar pelo CI, que hoje roda DEPOIS do fato.
#
# Requer: gh autenticado com permissao de admin no repo.
set -euo pipefail

REPO="${REPO:-$(gh repo view --json nameWithOwner --jq .nameWithOwner)}"
BRANCH="${BRANCH:-main}"
CHECK="${1:-}"

info() { printf '  %s\n' "$*"; }

echo "Repositorio: ${REPO}  (branch protegida: ${BRANCH})"
echo

if [ "$CHECK" = "--check" ]; then
  echo "== Estado atual =="
  gh api "repos/${REPO}" \
    --jq '"  delete_branch_on_merge: \(.delete_branch_on_merge)\n  visibility: \(.visibility)"'
  if gh api "repos/${REPO}/branches/${BRANCH}/protection" >/dev/null 2>&1; then
    gh api "repos/${REPO}/branches/${BRANCH}/protection" --jq '
      "  required checks: \(.required_status_checks.contexts // [] | join(", "))
  strict (exige branch atualizada): \(.required_status_checks.strict)
  exige PR: \(.required_pull_request_reviews != null)
  force push: \(.allow_force_pushes.enabled)"'
  else
    info "protecao de ${BRANCH}: NENHUMA  <-- um push direto vai para producao"
  fi
  exit 0
fi

echo "== 1. Deletar a branch automaticamente ao mergear o PR =="
gh api -X PATCH "repos/${REPO}" -F delete_branch_on_merge=true --jq '"  ok: \(.delete_branch_on_merge)"'

echo "== 2. Proteger a ${BRANCH} =="
# `strict: true` obriga a branch a estar atualizada com a main antes do merge —
# e o que impede o "verde individual, vermelho depois de integrar".
# `enforce_admins: false` de proposito: projeto solo, o dono precisa de uma
# saida de emergencia. A protecao segue valendo para o fluxo normal.
# 0 aprovacoes: exige o PR (bloqueia push direto) sem travar quem trabalha sozinho.
gh api -X PUT "repos/${REPO}/branches/${BRANCH}/protection" --input - >/dev/null <<'JSON'
{
  "required_status_checks": { "strict": true, "contexts": ["Tests + Pint"] },
  "enforce_admins": false,
  "required_pull_request_reviews": {
    "required_approving_review_count": 0,
    "dismiss_stale_reviews": true
  },
  "restrictions": null,
  "allow_force_pushes": false,
  "allow_deletions": false,
  "required_conversation_resolution": true
}
JSON
info "ok: PR obrigatorio, check \"Tests + Pint\" obrigatorio, force-push e delecao bloqueados"

echo
echo "== 3. Branches ja mergeadas (candidatas a limpeza) =="
git fetch origin --prune >/dev/null 2>&1 || true
mergeadas=$(git branch -r --merged "origin/${BRANCH}" \
  | grep -v "origin/${BRANCH}\$" | grep -v HEAD | sed 's|origin/||' | tr -d ' ' || true)

if [ -z "$mergeadas" ]; then
  info "nenhuma — arvore limpa."
else
  printf '%s\n' "$mergeadas" | sed 's/^/  /'
  echo
  info "para remover: bash bin/limpar-branches.sh"
fi
