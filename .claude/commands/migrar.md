---
description: Aplica migrations pendentes no container Docker (confere status antes).
argument-hint: "[--pretend | --seed] (opcional)"
allowed-tools: Bash(docker exec meu-negocio-app *)
---

Aplique as migrations **dentro do container** `meu-negocio-app`.

1. Antes, mostre o que esta pendente: `docker exec meu-negocio-app php artisan migrate:status`.
2. Aplique: `docker exec meu-negocio-app php artisan migrate $ARGUMENTS` (use `--force` se o ambiente
   for nao-interativo; `--seed` se o usuario pedir dados).
3. Reporte quais migrations rodaram.

Lembrete: toda migration deve ter `down()` reversivel. Se for destrutiva, confirme com o usuario antes.
