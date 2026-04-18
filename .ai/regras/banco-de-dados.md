# Regras de Banco de Dados

## Campos obrigatorios

| Campo | Quando | Motivo |
|-------|--------|--------|
| rede_id | Sempre | Isolamento de tenant |
| empresa_id | Dados de empresa | Isolamento de sub-tenant |

## Foreign keys

- `rede_id` → `redes.id` (sempre)
- `empresa_id` → `empresas.id` (quando aplicavel)
- Usar `cascadeOnDelete` para relacoes fortes (empresa dentro de rede)
- Usar `nullOnDelete` para relacoes opcionais (venda_pacote_id em agendamentos)

## Indices

- Indice composto `[rede_id, empresa_id]` em tabelas com ambos
- Indice em colunas de FK frequentemente filtradas
- Indice em colunas de data usadas em queries de periodo

## Tipos de coluna

| Dado | Tipo MySQL | Exemplo |
|------|-----------|---------|
| Nomes | string(200) | nome do cliente |
| Status/tipo | string(20) | status do agendamento |
| Telefone | string(20) | telefone do cliente |
| Documento | string(20) | CPF/CNPJ |
| Valores | decimal(10,2) | valor do servico |
| Quantidades | integer | quantidade em estoque |
| Duracao | integer | minutos |
| Flags | boolean | ativo, tem_estoque |
| Texto longo | text | observacoes |
| Data | date | data da despesa |
| Data+hora | datetime | inicio do agendamento |

## SoftDeletes

Usar em entidades principais: redes, empresas, usuarios, clientes, servicos, agendamentos, vendas, pagamentos, despesas, produtos.

Nao usar em: movimentos_estoque, movimentos_caixa, baixas_pagamento (registros permanentes).

## Convencoes de nomeacao

- Tabelas: plural, snake_case, portugues (`clientes`, `vendas_pacote`)
- Colunas: singular, snake_case (`nome`, `valor_total`, `forma_pagamento`)
- FKs: `{tabela_singular}_id` (`cliente_id`, `servico_id`)
- Timestamps: `created_at`, `updated_at`, `deleted_at`

## Nunca

- Nunca criar tabela sem rede_id
- Nunca criar tabela sem perguntar ao usuario
- Nunca misturar dados entre redes
- Nunca confiar em input para tenant (usar usuario logado)
