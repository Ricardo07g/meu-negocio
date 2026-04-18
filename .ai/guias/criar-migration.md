# Guia: Criar Migration

Convencoes para criar migrations no projeto.

## Localizacao

- Migrations de modulo: `app/Modules/{NomeModulo}/Migrations/`
- Migrations globais: `database/migrations/` (apenas Laravel/Spatie)
- Carregadas automaticamente pelo `ModuleServiceProvider`

## Nomeacao

```
{YYYY}_{MM}_{DD}_{sequencia}_create_{tabela}_table.php
{YYYY}_{MM}_{DD}_{sequencia}_add_{campo}_to_{tabela}_table.php
{YYYY}_{MM}_{DD}_{sequencia}_remove_{campo}_from_{tabela}_table.php
```

## Template: criar tabela

```php
Schema::create('{tabela}', function (Blueprint $table) {
    $table->id();

    // Tenant (obrigatorio)
    $table->unsignedBigInteger('rede_id');
    $table->unsignedBigInteger('empresa_id'); // se pertence a empresa

    // Campos do modulo
    $table->string('nome', 200);
    // ...

    $table->timestamps();
    $table->softDeletes(); // se aplicavel

    // Foreign keys
    $table->foreign('rede_id')->references('id')->on('redes');
    $table->foreign('empresa_id')->references('id')->on('empresas');

    // Indices
    $table->index(['rede_id', 'empresa_id']);
});
```

## Regras obrigatorias

1. **Sempre** incluir `rede_id` com FK para `redes`
2. **Incluir** `empresa_id` com FK para `empresas` quando dado pertence a empresa
3. **Indice composto** `[rede_id, empresa_id]` em tabelas com ambos
4. **SoftDeletes** em tabelas de entidades principais (clientes, servicos, etc.)
5. **Nunca** criar tabela sem perguntar ao usuario

## Tipos de coluna usados no projeto

| Tipo | Uso |
|------|-----|
| string(200) | nomes |
| string(20) | status, tipo, telefone, documento |
| string(100) | cidade, complemento, nome curto |
| string(50) | codigo, codigo_barras |
| decimal(10,2) | valores monetarios |
| integer | quantidades, duracao (minutos) |
| boolean | flags (ativo, tem_estoque, etc.) |
| text | observacoes, descricao |
| date | datas sem hora |
| datetime | datas com hora (inicio, fim) |

## Convencoes de FK

| Padrao | Uso |
|--------|-----|
| `cascadeOnDelete` | rede_id em empresas, empresa em sub-recursos |
| `nullOnDelete` | referencias opcionais (venda_pacote_id em agendamentos) |
| nullable | campos opcionais (cliente_id em venda_produto) |

## Exemplo real

Migration do modulo Caixa:
```
2026_03_29_100001_create_caixas_table.php
2026_03_29_100002_create_baixas_pagamento_table.php
2026_03_29_100003_create_movimentos_caixa_table.php
```

Sequencia numerica no mesmo dia para garantir ordem.
