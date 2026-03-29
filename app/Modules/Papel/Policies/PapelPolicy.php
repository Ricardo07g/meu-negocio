<?php

namespace App\Modules\Papel\Policies;

use App\Modules\Usuario\Models\Usuario;
use Spatie\Permission\Models\Role;

class PapelPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('papel.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('papel.criar');
    }

    public function update(Usuario $usuario, Role $papel): bool
    {
        return $usuario->can('papel.editar');
    }

    public function delete(Usuario $usuario, Role $papel): bool
    {
        return $usuario->can('papel.excluir');
    }
}
