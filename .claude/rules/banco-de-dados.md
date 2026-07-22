---
paths:
  - "**/Migrations/**"
  - "database/**"
---

# Banco de dados e migrations

Convencoes confirmadas nas migrations reais. Para o porque das colunas de tenant, ver
`.claude/rules/multi-tenant-seguranca.md`.

## Onde ficam as migrations
- **Modulo**: `app/Modules/{Modulo}/Migrations/` — auto-carregadas pelo `ModuleServiceProvider`.
  E onde mora 99% das migrations (uma por tabela/alteracao).
- **Global**: `database/migrations/` — APENAS infra Laravel/Spatie (cache, jobs, permission_tables,
  activity_log) e migrations transversais legadas (soft deletes em lote, renomeacoes de enum).
- Banco de dev = MySQL 8; testes = SQLite in-memory (`phpunit.xml`).

## Nomeacao
`{YYYY}_{MM}_{DD}_{sequencia}_create_{tabela}_table.php`,
`..._add_{campo}_to_{tabela}_table.php`, `..._remove_{campo}_from_{tabela}_table.php`. Sequencia
numerica no mesmo dia garante ordem (ex.: `..._100001_`, `..._100002_`). Tabelas: plural snake_case
em portugues (`clientes`, `vendas_etapas`, `parcelas_pagamento`). Colunas: singular snake_case. FKs:
`{tabela_singular}_id`.

## Colunas de tenant (obrigatorias)
- **`rede_id`** SEMPRE: `$table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();`
- **`empresa_id`** em tabelas transacionais (Agendamento, Venda, Pagamento, Despesa, Caixa, Estoque):
  `$table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();`
  Catalogo (Cliente, Servico, Produto, Categoria*) NAO tem `empresa_id`.
- **Indice composto** `$table->index(['rede_id', 'empresa_id']);` em toda tabela com ambos.
- Nota historica: tabelas antigas nasceram com `conta_id` e foram renomeadas para `rede_id` pela
  migration `rename_contas_to_redes` (Tenant). Tabelas novas ja usam `rede_id` direto.

## Foreign keys (ADR-0006)
Padrao real = `foreignId(...)->constrained('tabela')->{comportamento}`:
- **`cascadeOnDelete()`** — relacoes fortes: `rede_id`, `empresa_id` (filho morre com o pai).
- **`nullOnDelete()`** + `nullable()` — referencias opcionais: `cliente_id`, origens de Pagamento
  (`agendamento_id`, `venda_etapas_id`, `venda_produto_id`).
- **restrict** (default do `constrained()` sem modificador) — FKs estruturais que nao devem cascatear
  nem zerar (ex.: `servico_id`, `atendente_id`/`usuario_id`, `produto_id`).

## softDeletes
- **COM** `$table->softDeletes()`: entidades principais — redes, empresas, usuarios, clientes,
  servicos, agendamentos, vendas_etapas, vendas_produto, pagamentos, despesas, produtos, faturas,
  formas_pagamento, contas, recebiveis.
- **SEM**: registros append-only/permanentes — movimentos_estoque, lancamentos (razao unificado —
  ADR-0010), baixas_pagamento, baixas_despesa, parcelas (e tabelas pivot/infra).

## Tipos de coluna usados
| Tipo | Uso |
|------|-----|
| `string(200)` | nomes |
| `string(20)` | status, tipo, telefone, documento, forma/condicao de pagamento |
| `string(100)` / `string(50)` | cidade/complemento, codigo/codigo_barras |
| `decimal(10,2)` | valores monetarios (`->default(0)` quando agregador) |
| `integer` | quantidades, duracao (minutos), qtd_etapas |
| `boolean` | flags (`ativo`, `atende`, `tem_estoque`, `tem_financeiro`) |
| `text` | observacoes/descricao (nullable) |
| `date` | datas sem hora (`data`, `mes_referencia` = dia 1, `data_vencimento`) |
| `datetime` | data+hora (`inicio`, `fim`, `fechado_em`) |

## Regras
1. SEMPRE incluir `rede_id` (FK `redes`, cascade); SEMPRE perguntar antes de criar tabela nova.
2. `empresa_id` (FK `empresas`, cascade) quando a tabela for transacional.
3. Indice composto `[rede_id, empresa_id]`; indexar FKs filtradas e colunas de data de periodo.
4. **`down()` sempre reversivel** (`Schema::dropIfExists` ou `renameColumn`/`dropColumn` inverso);
   hook do projeto lembra de `down()` ao editar migration. Use `Schema::hasTable/hasColumn` em
   alteracoes condicionais.
5. `declare(strict_types=1)` no topo; classe anonima `return new class extends Migration`.
