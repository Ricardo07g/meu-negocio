---
description: Porta de qualidade antes do PR — Pint, PHPStan e testes no Docker, com resumo.
allowed-tools: Bash(docker exec meu-negocio-app *), Bash(git *)
---

Execute a porta de qualidade do projeto, **na ordem**, parando no primeiro vermelho e reportando a causa:

1. **Estilo (Pint)**: `docker exec meu-negocio-app vendor/bin/pint --test`
2. **Analise estatica (PHPStan)** — apenas se o binario existir:
   `docker exec meu-negocio-app sh -c '[ -f vendor/bin/phpstan ] && vendor/bin/phpstan analyse --no-progress || echo "PHPStan ainda nao instalado (Fase 2) — pulando"'`
3. **Testes**: `docker exec meu-negocio-app php artisan test`

Depois dos comandos, siga a skill **checklist-pre-pr** para os itens nao-mecanicos (tenancy via
`/auditar-tenancy`, migrations com `down()`, docs, commits no padrao `tipo(modulo):`).

Entregue um resumo final com cada item como ✅ / ⚠️ / ❌ e o que ainda falta antes de abrir o PR.
Nunca conclua "pronto para PR" sem a saida real dos tres passos.
