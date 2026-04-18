# Modulo: Despesa

CRUD de despesas da empresa. Vinculavel ao caixa via MovimentoCaixa.

## Localizacao

`app/Modules/Despesa/`

## Camadas

| Camada | Arquivos |
|--------|----------|
| Models | Despesa.php |
| Controllers | DespesaController.php |
| Services | DespesaService.php |
| DTOs | CriarDespesaData.php, AtualizarDespesaData.php |
| Requests | CriarDespesaRequest.php, AtualizarDespesaRequest.php |
| Policies | DespesaPolicy.php |
| Views | index, create, edit |
| Migrations | create_despesas_table |

## Model: Despesa

- Tabela: `despesas`
- Traits: PertenceARede, PertenceAEmpresa, SoftDeletes
- Fillable: rede_id, empresa_id, nome, valor, data
- Casts: valor → decimal:2, data → date

## DespesaService

CRUD simples + `listarPorPeriodo(inicio, fim)` para filtro por data.

## Schema: despesas

| Coluna | Tipo | Default |
|--------|------|---------|
| id | bigint PK | auto |
| rede_id | FK redes | — |
| empresa_id | FK empresas | — |
| nome | string(200) | — |
| valor | decimal(10,2) | — |
| data | date | — |
| deleted_at | timestamp | null |

Indices: `[rede_id, empresa_id]`, `data`

## Integracao com Caixa

MovimentoCaixa tem campo `despesa_id` nullable.
Quando despesa e registrada como saida de caixa, o movimento referencia a despesa.
