---
name: validar-implementacao
description: "Valida uma implementacao recem-feita do Meu Negocio ponta-a-ponta: roda os testes do modulo tocado + Pint + PHPStan e, quando ha tela, um smoke headless. Use SEMPRE depois de implementar ou corrigir uma feature — antes de dizer 'pronto' — para confirmar que funciona e nao regrediu, mesmo que o usuario nao peca explicitamente. Mais leve e focada que a checklist-pre-pr (que e a porta completa de PR)."
---

# Validar implementacao — Meu Negocio

Fecha o loop de uma feature: prova que o que voce acabou de mexer **funciona e nao regrediu**, com
saida real. Difere da `checklist-pre-pr` (porta completa antes do PR): aqui o foco e o **diff atual**,
rapido e direcionado ao que mudou. Acurácia vem de medir, nao de presumir.

## 0. Descobrir o escopo
`git diff --name-only` (e `git status`) para listar os arquivos tocados e inferir o(s) modulo(s)
(`app/Modules/<Modulo>/...`) e se houve mudanca em **View/Controller/rota** (tem tela a validar).

## 1. Testes do que mudou (sempre)
- Modulo especifico: `docker exec meu-negocio-app php artisan test --filter=<Modulo>`.
- Mudanca transversal (traits, BaseModel, middleware, support): rode a **suite inteira**
  `docker exec meu-negocio-app php artisan test`.
- Cole a saida real (passou/falhou). Vermelho aqui = pare e use a skill `depurar`.

## 2. Estilo e analise estatica (sempre)
- `docker exec meu-negocio-app vendor/bin/pint --test` → zero diffs (o hook ja formata ao editar,
  mas confirme).
- `docker exec meu-negocio-app vendor/bin/phpstan analyse --no-progress` → sem erro novo (ha baseline).

## 3. Smoke da tela (se tocou View/Controller/rota)
Valida que a tela responde 200, sem erro de console, com o elemento-chave presente. O script
`scripts/smoke.cjs` (puppeteer-core) ja faz login e checa cada rota:

```
node ${CLAUDE_SKILL_DIR}/scripts/smoke.cjs "/pagamentos" "table"   # rota [seletor-chave opcional]
```

- Pre-requisitos (no **host**, nao no container): `google-chrome` instalado e `puppeteer-core`
  disponivel (`npm i -g puppeteer-core` ou em `node_modules`). Variaveis opcionais: `CHROME_BIN`,
  `BASE_URL` (default `http://localhost:8080`), `MN_EMAIL`/`MN_PASSWORD` (default
  `admin@teste.com`/`password`).
- Sem chrome/puppeteer: o script sai com aviso claro — entao **cubra a tela com um teste de view**
  (200 + dado-chave) em vez do smoke, e siga.

## 4. Tenancy (se tocou dado tenant-aware)
Mudou Model/Service/Controller/query com `rede_id`/`empresa_id`? Rode `/auditar-tenancy` no diff (ou o
subagente `tenancy-security-reviewer`). Veja `.claude/rules/multi-tenant-seguranca.md`.

## Saida
Resumo com cada dimensao como ✅/⚠️/❌ (testes, pint, phpstan, smoke, tenancy) e a saida real colada.
Se algo deu ❌, **nao declare validado** — corrija (skill `depurar`) e rode de novo. So entao conclua.
