---
name: criar-migration
description: "Cria migration no padrao do Meu Negocio (modulo em app/Modules/{M}/Migrations, colunas de tenant, FKs por convencao, down() reversivel). Use quando precisar adicionar/alterar tabela ou coluna (ex.: 'adiciona o campo X em Y', 'cria a tabela de Z', 'preciso de uma migration pra ...')."
argument-hint: "<descricao> (ex.: add desconto_max em produtos)"
---

# Criar migration — Meu Negocio

Migrations seguem convencao forte. **Nunca crie/derrube tabela sem confirmar com o usuario.**

## Local e nome
- Migration de modulo: `app/Modules/{Modulo}/Migrations/` (carregada pelo `ModuleServiceProvider`).
  Globais (Laravel/Spatie) ficam em `database/migrations/`.
- Nome: `{YYYY}_{MM}_{DD}_{seq}_create_{tabela}_table.php`,
  `..._add_{campo}_to_{tabela}_table.php`, `..._remove_{campo}_from_{tabela}_table.php`.
  Use `seq` no mesmo dia para garantir ordem.

## Regras de tenant (obrigatorias)
- `rede_id` **sempre** com FK para `redes`. `empresa_id` quando o dado e **transacional**
  (ver catalogo x transacional em `.claude/rules/multi-tenant-seguranca.md`).
- Padrao real do projeto (nao `unsignedBigInteger` + `->foreign()` manual):
  ```php
  $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
  $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete(); // se transacional
  ```
- Indice composto `$table->index(['rede_id', 'empresa_id'])` quando houver ambos.
- `$table->softDeletes()` em entidades principais.
- FKs conforme **ADR-0006** (`docs/ADR/`): `cascadeOnDelete` para donos, `nullOnDelete` para
  referencias opcionais (ex.: `venda_etapas_id`), `restrict` quando apagar quebraria integridade.

## `down()` reversivel — sempre
Toda migration tem `down()` que desfaz exatamente o `up()` (o hook `guard-migration` lembra disso).
Em `add`/`remove` de coluna, derrube a FK antes da coluna quando aplicavel.

## Aplicar e validar
- `/migrar` ou `docker exec meu-negocio-app php artisan migrate` (use `--pretend` para conferir SQL).
- O **dev DB pode ter drift** de migrations — se `migrate` falhar la, valide a migration pela suite
  (SQLite in-memory recria do zero): `docker exec meu-negocio-app php artisan test`.
- Tipos usados no projeto: `string(200)` nomes, `string(20)` status/telefone, `decimal(10,2)` valores,
  `integer` quantidades, `boolean` flags, `text` observacoes, `date`/`datetime` datas.

## Saida
O arquivo criado, o SQL conferido (`--pretend` ou migrate aplicado) e a suite verde. Veja tambem
`.claude/rules/banco-de-dados.md`.
