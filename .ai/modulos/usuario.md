# Modulo: Usuario

CRUD de usuarios do sistema. Usuarios pertencem a uma rede e empresa.

## Localizacao

`app/Modules/Usuario/`

## Camadas

| Camada | Arquivos |
|--------|----------|
| Models | Usuario.php |
| Controllers | UsuarioController.php |
| Services | UsuarioService.php |
| Actions | CriarUsuarioAction.php |
| DTOs | CriarUsuarioData.php, AtualizarUsuarioData.php |
| Requests | CriarUsuarioRequest.php, AtualizarUsuarioRequest.php |
| Policies | UsuarioPolicy.php |
| Views | index, create, edit, show |
| Migrations | create_usuarios_table (+ sessions, password_reset_tokens) |

## Model: Usuario

- Tabela: `usuarios`
- Traits: HasRoles (Spatie), Notifiable, PertenceARede, PertenceAEmpresa, SoftDeletes
- Fillable: rede_id, empresa_id, nome, email, password, ativo, atende
- Hidden: password, remember_token
- Casts: password → hashed, ativo → boolean, atende → boolean

## Campos especiais

- `ativo` (bool) — se false, login e bloqueado
- `atende` (bool) — se true, aparece como atendente nos agendamentos

## Regras de negocio

### CriarUsuarioAction
1. Valida limite do plano (ValidarPlanoAction 'usuario')
2. Cria usuario com rede_id e empresa_id
3. `ativo` default true
4. `atende` default true se papel for Admin, senao usa valor informado
5. Atribui papel via `assignRole()`

### UsuarioService
- `criar()` → delega para CriarUsuarioAction
- `atualizar()` → atualiza campos, password so se informado, syncRoles
- `excluir()` → soft delete

## Schema: usuarios

| Coluna | Tipo | Default |
|--------|------|---------|
| id | bigint PK | auto |
| rede_id | FK redes | — |
| empresa_id | FK empresas | null |
| nome | string(200) | — |
| email | string (unique) | — |
| password | string | — |
| ativo | bool | true |
| atende | bool | false |
| remember_token | string | null |
| deleted_at | timestamp | null |

Indice composto: `[rede_id, empresa_id]`
