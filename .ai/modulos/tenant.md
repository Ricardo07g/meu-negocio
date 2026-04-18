# Modulo: Tenant

Gerencia Rede, Empresa e Plano. E o nucleo do multi-tenant.

## Localizacao

`app/Modules/Tenant/`

## Camadas

| Camada | Arquivos |
|--------|----------|
| Models | Rede.php, Empresa.php, Plano.php |
| Controllers | EmpresaController.php |
| Services | RedeService.php, EmpresaService.php, PlanoService.php |
| Actions | CriarEmpresaAction.php, ValidarPlanoAction.php |
| DTOs | CriarRedeData, AtualizarRedeData, CriarEmpresaData, AtualizarEmpresaData |
| Requests | CriarEmpresaRequest, AtualizarEmpresaRequest |
| Policies | RedePolicy, EmpresaPolicy, PlanoPolicy |
| Views | index, create, edit, show |
| Migrations | planos, redes (ex-contas), empresas, rename contas→redes |

## Models

### Rede
- Tabela: `redes`
- Traits: SoftDeletes
- Fillable: nome, plano_id, status
- Casts: status → StatusRede
- Relacoes: plano (belongsTo Plano), empresas (hasMany), usuarios (hasMany)

### Empresa
- Tabela: `empresas`
- Traits: PertenceARede, SoftDeletes
- Fillable: rede_id, nome, documento, telefone, email
- Relacoes: usuarios, clientes, servicos, agendamentos, pagamentos, despesas, produtos (todos hasMany)

### Plano
- Tabela: `planos`
- Traits: nenhum
- Fillable: nome, max_empresas, max_usuarios, tem_estoque, tem_financeiro, tem_relatorios
- Casts: tem_estoque/tem_financeiro/tem_relatorios → boolean
- Relacoes: redes (hasMany)

## Regras de negocio

### RedeService.criar()
1. Busca plano free (ou informado)
2. Cria a rede com status ativa
3. Cria empresa padrao
4. Cria usuario admin
5. Cria 6 categorias de produto padrao (Cabelo, Corpo, Rosto, Unhas, Consumiveis, Outros)
6. Tudo em transacao

### CriarEmpresaAction
- Valida limite do plano antes de criar (ValidarPlanoAction)
- Lanca PlanoLimiteException se exceder

### ValidarPlanoAction
- Recursos contaveis (empresa, usuario): compara count vs max (0 = ilimitado)
- Feature flags (estoque, financeiro, relatorios): verifica boolean
- Lanca PlanoLimiteException

## Schema

### planos
| Coluna | Tipo | Default |
|--------|------|---------|
| id | bigint PK | auto |
| nome | string(100) | — |
| max_empresas | int | 1 |
| max_usuarios | int | 2 |
| tem_estoque | bool | false |
| tem_financeiro | bool | false |
| tem_relatorios | bool | false |

### redes
| Coluna | Tipo | Default |
|--------|------|---------|
| id | bigint PK | auto |
| nome | string(200) | — |
| plano_id | FK planos | — |
| status | string(20) | 'ativa' |
| deleted_at | timestamp | null |

### empresas
| Coluna | Tipo | Default |
|--------|------|---------|
| id | bigint PK | auto |
| rede_id | FK redes (cascade) | — |
| nome | string(200) | — |
| documento | string(20) | null |
| telefone | string(20) | null |
| email | string | null |
| deleted_at | timestamp | null |
