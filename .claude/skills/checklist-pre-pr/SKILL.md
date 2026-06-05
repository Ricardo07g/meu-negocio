---
name: checklist-pre-pr
description: "Prepara uma branch do Meu Negocio para abrir PR: roda a porta de qualidade (Pint, PHPStan, testes), audita tenancy, confere migrations e docs, e organiza commits. Use quando o usuario for abrir PR, fechar uma feature, 'preparar para revisao/merge', ou perguntar se o codigo esta pronto para subir."
---

# Checklist pre-PR — Meu Negocio

Garante que a branch sobe **verde** e sem regressao de isolamento. A execucao mecanica esta no
comando `/pre-pr`; esta skill e o roteiro completo (o que checar e por que).

## 1. Porta de qualidade (tudo no Docker)

Rode e exija sucesso antes de seguir:
- **Estilo**: `docker exec meu-negocio-app vendor/bin/pint --test` → zero diffs.
- **Analise estatica**: `docker exec meu-negocio-app vendor/bin/phpstan analyse --no-progress` → sem erros no nivel configurado.
- **Testes**: `docker exec meu-negocio-app php artisan test` → todos verdes.

Atalho: `/pre-pr` encadeia os tres e resume. So prossiga com tudo verde — o CI repete esses passos e
vai barrar o PR se algo falhar.

## 2. Auditoria de tenancy

Para mudancas que tocam dados tenant-aware (Model/Service/Controller/query), rode a auditoria de
isolamento — comando `/auditar-tenancy` ou o subagente **tenancy-security-reviewer**. Confirme:
nenhum vazamento `rede_id`/`empresa_id`, Policies registradas, `authorize()` nas acoes mutaveis.

## 3. Migrations e dados

- Toda migration nova tem `down()` reversivel?
- Precisa de seed/factory novo para o CI conseguir testar? (a suite roda em SQLite in-memory).
- Nada de SQL exclusivo de MySQL que quebre no SQLite dos testes.

## 4. Documentacao

- Se a **arquitetura** mudou (novo modulo, novo padrao, mudanca de escopo de tenancy), atualize
  `CLAUDE.md` e, se for decisao relevante, registre um ADR em `docs/ADR/`.
- Mudou automacao (agents/skills/hooks/commands)? Atualize `docs/AUTOMACAO.md`.

## 5. Commits e PR

- Commits no padrao `tipo(modulo): mensagem` (`feat`/`fix`/`refactor`/`docs`/`chore`/`test`),
  agrupados por tema (nao um commitao).
- Mensagem do PR: o que mudou, por que, como testar. Confirme que a branch nao e a `main`.

## Saida

Um resumo objetivo: cada item acima como ✅/⚠️/❌, com o que falta corrigir antes de abrir o PR.
Nunca declare "pronto" sem colar a saida real de Pint/PHPStan/testes verdes.
