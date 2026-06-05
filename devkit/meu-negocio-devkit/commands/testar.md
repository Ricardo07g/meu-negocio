---
description: Roda a suite de testes (PHPUnit) no container Docker; opcionalmente filtra por nome.
argument-hint: "[filtro/NomeDoTeste] (vazio = suite inteira)"
allowed-tools: Bash(docker exec meu-negocio-app *)
---

Rode os testes do projeto **dentro do container** `meu-negocio-app` (nao ha PHP no host).

- Sem argumento: `docker exec meu-negocio-app php artisan test`
- Com argumento `$ARGUMENTS`: `docker exec meu-negocio-app php artisan test --filter=$ARGUMENTS`

Ao terminar, resuma: quantos passaram/falharam e, havendo falhas, liste os testes vermelhos com a
causa provavel. Nao declare sucesso sem a saida real do `artisan test`.
