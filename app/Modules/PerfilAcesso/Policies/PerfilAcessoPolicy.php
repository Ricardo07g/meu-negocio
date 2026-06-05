<?php

declare(strict_types=1);

namespace App\Modules\PerfilAcesso\Policies;

use App\Modules\Usuario\Models\Usuario;
use Spatie\Permission\Models\Role;

class PerfilAcessoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('papel.ver');
    }

    public function view(Usuario $usuario, Role $perfil): bool
    {
        return $usuario->can('papel.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('papel.criar');
    }

    public function update(Usuario $usuario, Role $perfil): bool
    {
        // O perfil "Admin" e do sistema: somente leitura (nao editavel pela UI).
        return $usuario->can('papel.editar') && $perfil->name !== 'Admin';
    }

    public function delete(Usuario $usuario, Role $perfil): bool
    {
        // O perfil "Admin" nao pode ser excluido.
        return $usuario->can('papel.excluir') && $perfil->name !== 'Admin';
    }
}
