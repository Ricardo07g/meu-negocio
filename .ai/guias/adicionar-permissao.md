# Guia: Adicionar Permissao/Papel

Como adicionar novas permissoes ou papeis ao sistema.

## Formato de permissao

```
{recurso}.{acao}
```

Exemplos: `cliente.ver`, `agendamento.cancelar`, `financeiro.relatorio`

## Acoes padrao por recurso

```
{recurso}.ver
{recurso}.criar
{recurso}.editar
{recurso}.excluir
```

Acoes extras quando necessario: `.cancelar`, `.relatorio`, `.configurar`, etc.

## Passo 1: Criar no seeder/migration

Permissoes sao registradas via Spatie. Adicionar ao seeder:

```php
Permission::firstOrCreate(['name' => '{recurso}.ver', 'guard_name' => 'web']);
Permission::firstOrCreate(['name' => '{recurso}.criar', 'guard_name' => 'web']);
Permission::firstOrCreate(['name' => '{recurso}.editar', 'guard_name' => 'web']);
Permission::firstOrCreate(['name' => '{recurso}.excluir', 'guard_name' => 'web']);
```

## Passo 2: Atribuir a papeis

Seguir a matriz de permissoes. Exemplo:

```php
$admin = Role::findByName('Admin');
$admin->givePermissionTo(['{recurso}.ver', '{recurso}.criar', '{recurso}.editar', '{recurso}.excluir']);

$gerente = Role::findByName('Gerente');
$gerente->givePermissionTo(['{recurso}.ver', '{recurso}.criar']);

$visualizador = Role::findByName('Visualizador');
$visualizador->givePermissionTo(['{recurso}.ver']);
```

## Passo 3: Usar na Policy

```php
public function viewAny(Usuario $user): bool
{
    return $user->can('{recurso}.ver');
}
```

## Passo 4: Usar no menu (layout)

```blade
@can('{recurso}.ver')
    <li><a href="{{ route('{recurso}.index') }}">Nome do Recurso</a></li>
@endcan
```

## Passo 5: Documentar

Adicionar novas permissoes em:
- `INSTRUCTIONS/PERMISSIONS.md`
- `.ai/contexto/permissoes-e-papeis.md`

## Papeis existentes (PapelEnum)

Admin, Gerente, Profissional, Recepcao, Financeiro, Estoque, Visualizador

Para criar novo papel:
1. Adicionar ao `PapelEnum`
2. Criar Role no seeder
3. Atribuir permissoes adequadas
4. Documentar na matriz de permissoes
