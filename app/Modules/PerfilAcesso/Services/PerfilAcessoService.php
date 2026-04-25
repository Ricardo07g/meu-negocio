<?php

namespace App\Modules\PerfilAcesso\Services;

use App\Exceptions\NegocioException;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PerfilAcessoService
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
        $perfil = Role::create(['name' => $nome, 'guard_name' => 'web']);
        $perfil->syncPermissions($permissoes);

        return $perfil;
    }

    public function atualizar(Role $perfil, string $nome, array $permissoes = []): Role
    {
        if ($perfil->name === 'Admin') {
            throw new NegocioException('O perfil Admin não pode ser editado.');
        }

        $perfil->update(['name' => $nome]);
        $perfil->syncPermissions($permissoes);

        return $perfil->fresh();
    }

    public function excluir(Role $perfil): void
    {
        if ($perfil->name === 'Admin') {
            throw new NegocioException('O perfil Admin não pode ser excluído.');
        }

        if ($perfil->users()->count() > 0) {
            throw new NegocioException('Este perfil possui usuários vinculados. Remova-os antes de excluir.');
        }

        $perfil->delete();
    }
}
