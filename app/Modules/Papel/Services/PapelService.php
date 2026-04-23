<?php

namespace App\Modules\Papel\Services;

use App\Exceptions\NegocioException;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PapelService
{
    public function listar(): Collection
    {
        return Role::with('permissions')->get();
    }

    /**
     * Retorna as permissoes agrupadas pelo prefixo antes do primeiro ponto
     * (ex.: "cliente.criar" -> grupo "cliente").
     */
    public function permissoesAgrupadas(): Collection
    {
        return Permission::orderBy('name')->get()->groupBy(
            fn (Permission $p) => explode('.', $p->name)[0]
        );
    }

    public function criar(string $nome, array $permissoes = []): Role
    {
        $papel = Role::create(['name' => $nome, 'guard_name' => 'web']);
        $papel->syncPermissions($permissoes);

        return $papel;
    }

    public function atualizar(Role $papel, string $nome, array $permissoes = []): Role
    {
        if ($papel->name === 'Admin') {
            throw new NegocioException('O papel Admin não pode ser editado.');
        }

        $papel->update(['name' => $nome]);
        $papel->syncPermissions($permissoes);

        return $papel->fresh();
    }

    public function excluir(Role $papel): void
    {
        if ($papel->name === 'Admin') {
            throw new NegocioException('O papel Admin não pode ser excluído.');
        }

        if ($papel->users()->count() > 0) {
            throw new NegocioException('Este papel possui usuários vinculados. Remova-os antes de excluir.');
        }

        $papel->delete();
    }
}
