<?php

namespace App\Modules\Tenant\Policies;

use App\Modules\Tenant\Models\Empresa;
use App\Modules\Usuario\Models\Usuario;

class EmpresaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('empresa.ver');
    }

    public function view(Usuario $usuario, Empresa $empresa): bool
    {
        return $usuario->rede_id === $empresa->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $empresa->id)
            && $usuario->can('empresa.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('empresa.criar');
    }

    public function update(Usuario $usuario, Empresa $empresa): bool
    {
        return $usuario->rede_id === $empresa->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $empresa->id)
            && $usuario->can('empresa.editar');
    }

    public function delete(Usuario $usuario, Empresa $empresa): bool
    {
        return $usuario->rede_id === $empresa->rede_id
            && $usuario->can('empresa.excluir');
    }
}
