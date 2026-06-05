---
paths:
  - "app/Modules/Usuario/**"
---

# Modulo: Usuario

CRUD de usuarios da rede (admin) + "Meu Perfil" self-service. Usuario e a entidade autenticavel
(`config/auth.php` -> `App\Modules\Usuario\Models\Usuario`, guard `web`).

## Entidades & status
- Model `Usuario` (tabela `usuarios`). Estende `Authenticatable` (NAO BaseModel).
  Traits: `HasRoles` (Spatie), `Notifiable`, `RedeTrait`, `SoftDeletes`.
- **Rede-level apenas**: usa `RedeTrait` (global scope `rede_id`), **NAO** usa `EmpresaTrait`.
  Aplicar EmpresaTrait quebraria `auth()->user()` quando o contexto difere do `empresa_id` default.
- Fillable: `rede_id, empresa_id, nome, email, password, ativo, atende`.
  Casts: `password => hashed`, `ativo => boolean`, `atende => boolean`. Hidden: `password, remember_token`.
- `ativo` (bool, default true): se false, login bloqueado. `atende` (bool, default false): aparece como atendente em Agenda/Venda.

## Camadas-chave
- `UsuarioController` — CRUD admin; `index/create/store/edit/update/destroy` (NAO ha `show` — sempre edicao). `authorize(...)` em cada metodo.
- `PerfilController` — self-service "Meu Perfil": `index`, `atualizar` (nome/email), `atualizarSenha`.
- `UsuarioService` — `listar` (paginate 20), `buscar`, `criar` (delega Action), `atualizar`, `excluir` (soft delete).
- `CriarUsuarioAction` — valida plano (`ValidarPlanoAction->executar($rede, 'usuario')`), cria, `assignRole`, sincroniza pivot.
- `UsuarioData` (DTO unico criar/editar): `nome, email, ?password, ?empresa_id, ?papel, ?ativo, ?atende, ?empresas[]`.
- `SalvarUsuarioRequest` (`isMethod('post')` decide criar/editar). `AtualizarPerfilRequest`, `AtualizarSenhaPerfilRequest` (self-service).
- `UsuarioPolicy` — `viewAny/create` por permissao `usuario.*`; `update` exige mesma `rede_id` + `podeAcessarEmpresa($alvo->empresa_id)`; `delete` exige mesma `rede_id`.

## Regras de negocio / gotchas
- **Pivot `empresa_usuario`** (`rede_id, empresa_id, usuario_id`) e a fonte de verdade do conjunto de empresas
  acessiveis (relacao `empresas()`). `empresa_id` na tabela e so a empresa default ao logar (preferencia, NAO barreira de tenancy).
- `SalvarUsuarioRequest`: nao-admin exige `empresas` array com `min:1`; Admin nao precisa de pivot (acessa tudo). `empresas.*` validados como pertencentes a propria `rede_id`.
- Ao sincronizar pivot (Action e Service): o `sync` precisa preencher `rede_id` no pivot — `mapWithKeys(fn($id) => [(int)$id => ['rede_id' => $usuario->rede_id]])`.
- `CriarUsuarioAction`: `atende` default = `($papel === 'Admin')` quando nao informado; `ativo` sempre true na criacao.
- `Service::atualizar`: password so atualiza se informado; `syncRoles([papel])` so se papel presente; `empresas` so sincroniza se `!== null`.
- `scopeAtendentesDaEmpresa($empresaId)` — `atende=true` E (pivot contem a empresa OU Role `Admin`). Usado por Agenda/Venda.
- `podeAcessarEmpresa(?int)`: null => false; Admin => true; senao checa pivot. Usado por TODAS as Policies.
- Migration original cria `conta_id` (FK `contas`); renomeado para `rede_id`/`redes` por `Tenant/.../2026_03_22_300001_rename_contas_to_redes`. Cria tambem `password_reset_tokens` e `sessions`.
- `sendPasswordResetNotification` sobrescrito para enviar `RecuperacaoSenhaMailable` (branding) em vez da notification default.
- Rotas: `Route::resource('usuarios')->except(['show'])` sob `auth + verificar.rede + verificar.empresa`. Perfil: `GET/POST perfil` + `POST perfil/senha` (fora de `verificar.empresa`).

## Veja tambem
- `.claude/rules/multi-tenant-seguranca.md` — RedeTrait, pivot `empresa_usuario`, `podeAcessarEmpresa`, camadas de autorizacao, Policies.
- `.claude/rules/modulos/perfilacesso.md` — papeis/permissoes Spatie (slug `papel.*`).
