---
paths:
  - "app/Modules/PerfilAcesso/**"
---

# Modulo: PerfilAcesso

CRUD de perfis de acesso (papeis) e suas permissoes. Wrapper sobre o `Role`/`Permission` do
`spatie/laravel-permission` — **nao tem model proprio**. Renomeado do antigo modulo "Papel".

## Entidades & status
- Usa `Spatie\Permission\Models\Role` e `...\Permission` diretamente (guard `web`).
- `config/permission.php` -> `teams => false`: roles/permissions sao **globais ao DB**, NAO escopados por `rede_id`/`empresa_id`.
- Permissoes nomeadas `modulo.acao` (ex.: `cliente.criar`, `usuario.editar`). Catalogo completo em `database/seeders/PermissaoSeeder.php`.
- **Slug do proprio modulo e `papel.*`** (`papel.ver/criar/editar/excluir`) — mantido por compat com codigo legado, apesar do modulo se chamar PerfilAcesso.
- Role `Admin` e a unica seedada, com TODAS as permissoes (`$admin->syncPermissions($permissoes)`). Demais perfis sao criados pelo Admin via `/perfis-acesso`.

## Camadas-chave
- `PerfilAcessoController` — `index/create/store/show/edit/update/destroy`. Tem `show`. Type-hint `Role $perfilAcesso` (route-model binding).
- `PerfilAcessoService` — `listar` (Role::with('permissions')), `permissoesAgrupadas` (agrupa por prefixo `modulo.` + metadados de UI: icone Feather, rotulo), `criar`, `atualizar`, `excluir`.
- `PerfilAcessoPolicy` — registrada como `Role::class => PerfilAcessoPolicy::class` no `AppServiceProvider`. Metodos por permissao `papel.*`; `update`/`delete` adicionalmente bloqueiam `name === 'Admin'`.
- `SalvarPerfilAcessoRequest` (unico, `isMethod('post')`). Valida `name` (unique em `roles`, ignore no edit), `permissoes[]` (`exists:permissions,name`).

## Regras de negocio / gotchas
- **Perfil `Admin` e do sistema (read-only via UI)**: nao pode ser editado nem excluido. Defesa em dois pontos:
  Policy (`$perfil->name !== 'Admin'`) E Service (`atualizar`/`excluir` lançam `NegocioException`).
- **Adicionar novas permissoes e via seed** (`PermissaoSeeder`), nao pela UI. O Admin recebe-as automaticamente no `syncPermissions`.
- `excluir`: bloqueia se houver usuarios vinculados (`$perfil->users()->count() > 0`) -> `NegocioException`.
- `permissoesAgrupadas()` deriva o grupo do prefixo antes do primeiro `.` no nome da permissao; consts `ICONES`/`ROTULOS_MODULO`/`ROTULOS_ACAO` no Service controlam a UI. Ao criar nova permissao com prefixo novo, considere adicionar entradas la (fallback: `feather-circle` + `ucfirst`).
- Rota: `Route::resource('perfis-acesso', PerfilAcessoController::class)->parameters(['perfis-acesso' => 'perfil_acesso'])` — parametro de rota e `perfil_acesso` (usado em `$this->route('perfil_acesso')` no Request). Nao ha `PapelEnum` no projeto (lista de 7 papeis do doc antigo era stale).
- Views em `perfilacesso::` (`index/create/edit/show/_form`).

## Veja tambem
- `.claude/rules/multi-tenant-seguranca.md` — camada de Permissao (Policies + `can(...)`), registro de Policy no `AppServiceProvider`.
- `.claude/rules/modulos/usuario.md` — atribuicao de papel ao usuario (`assignRole`/`syncRoles`).
