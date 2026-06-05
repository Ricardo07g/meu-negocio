---
name: adicionar-permissao
description: "Adiciona permissao ou perfil de acesso no Meu Negocio (spatie/laravel-permission) no padrao recurso.acao, com Policy registrada e gate na UI. Use ao criar um recurso que precisa de permissoes, ou quando pedirem 'adiciona a permissao X', 'novo perfil/papel Y', 'cria um gate pra Z', 'so admin pode ...'."
argument-hint: "<recurso.acao ou nome do perfil>"
---

# Adicionar permissao / perfil — Meu Negocio

Roles e permissions sao **globais** (`teams => false`), nao tenant-scoped. Slug no formato
`recurso.acao`. Veja a secao "Permissoes" em `.claude/rules/multi-tenant-seguranca.md` e o modulo em
`.claude/rules/modulos/perfilacesso.md`.

## Nova permissao
1. **Seed**: adicione no seeder de permissoes (`Permission::firstOrCreate(['name' => 'recurso.acao',
   'guard_name' => 'web'])`). Acoes padrao: `.ver`, `.criar`, `.editar`, `.excluir`; extras conforme
   a regra (`.cancelar`, `.relatorio`, ...).
2. **Admin**: o role **`Admin`** e o unico seedado e deve receber todas as permissoes novas
   (`$admin->givePermissionTo([...])`). Os demais perfis NAO sao seedados — recebem permissoes pela
   UI em `/perfis-acesso` (Admin e somente-leitura na tela).
3. **Policy**: use a permissao no `XxxPolicy` (`return $user->can('recurso.acao');`) e **registre a
   Policy** em `app/Providers/AppServiceProvider.php` (`$policies`) — sem registro, a Policy nao vale.
4. **Controller**: `$this->authorize('acao', $model)` nos metodos. **Menu/Blade**: envolva com
   `@can('recurso.ver') ... @endcan`.
5. **Cache**: em testes, `app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions()`
   apos criar/atribuir.

## Novo perfil (Role)
Perfis nao-Admin sao criados pela UI (`/perfis-acesso`, validacao dinamica `exists:roles,name`). Em
seed/teste: `Role::firstOrCreate(['name' => 'X', 'guard_name' => 'web'])` + `syncPermissions([...])`.
**Nao existe `PapelEnum`** (era do modelo antigo).

## Convencoes / gotchas
- Slug `papel.*` foi mantido por compatibilidade apesar do modulo se chamar PerfilAcesso.
- `usuarios.atende` e flag **operacional** de atendente — nao e permissao/autorizacao.
- Ao mudar o catalogo de permissoes, atualize a rule `.claude/rules/modulos/perfilacesso.md`.

## Saida
Permissao/perfil criados no seeder, Policy registrada, gate aplicado, e um teste cobrindo o 403 do
caminho sem permissao (modelo: `tests/Feature/PerfilAcesso`). Cole a suite verde.
