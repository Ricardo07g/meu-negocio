# Modulo: Cliente

CRUD completo de clientes com dados pessoais e endereco.

## Localizacao

`app/Modules/Cliente/`

## Camadas

| Camada | Arquivos |
|--------|----------|
| Models | Cliente.php |
| Controllers | ClienteController.php |
| Services | ClienteService.php |
| Actions | CriarClienteAction.php, AtualizarClienteAction.php |
| DTOs | CriarClienteData.php, AtualizarClienteData.php |
| Requests | CriarClienteRequest.php, AtualizarClienteRequest.php |
| Policies | ClientePolicy.php |
| Views | index, create, edit, show |
| Migrations | create_clientes_table, add_campos_cliente_table |

## Model: Cliente

- Tabela: `clientes`
- Traits: PertenceARede, SoftDeletes
- Casts: data_nascimento → date, telefone_whatsapp → boolean
- Relacoes: agendamentos (hasMany), vendasPacote (hasMany), pagamentos (hasManyThrough via Agendamento)

## Campos

**Dados pessoais:** nome, telefone, telefone_whatsapp, email, data_nascimento, cpf, sexo, observacoes
**Endereco:** cep, estado, cidade, bairro, logradouro, numero, complemento

## Regras de negocio

### CriarClienteAction / AtualizarClienteAction
- Converte `data_nascimento` de formato "d/m/Y" para Carbon
- `telefone_whatsapp` default false
- Armazena endereco completo

## Schema: clientes

| Coluna | Tipo | Default |
|--------|------|---------|
| id | bigint PK | auto |
| rede_id | FK redes | — |
| nome | string(200) | — |
| telefone | string(20) | null |
| telefone_whatsapp | bool | false |
| email | string | null |
| data_nascimento | date | null |
| cpf | string(14) | null |
| sexo | string(10) | null |
| cep | string(9) | null |
| estado | string(2) | null |
| cidade | string(100) | null |
| bairro | string(100) | null |
| logradouro | string(200) | null |
| numero | string(20) | null |
| complemento | string(100) | null |
| observacoes | text | null |
| deleted_at | timestamp | null |

Nota: Cliente usa apenas `PertenceARede` (sem PertenceAEmpresa na trait, mas o rede_id garante isolamento).
