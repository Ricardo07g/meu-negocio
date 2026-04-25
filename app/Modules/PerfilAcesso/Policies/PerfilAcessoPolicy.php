<?php

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
        return $usuario->can('papel.editar');
    }

    public function delete(Usuario $usuario, Role $perfil): bool
    {
        return $usuario->can('papel.excluir');
    }
}
