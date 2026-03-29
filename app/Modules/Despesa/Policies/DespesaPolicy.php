<?php

namespace App\Modules\Despesa\Policies;

use App\Modules\Despesa\Models\Despesa;
use App\Modules\Usuario\Models\Usuario;

class DespesaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('despesa.ver');
    }

    public function view(Usuario $usuario, Despesa $despesa): bool
    {
        return $usuario->rede_id === $despesa->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $despesa->empresa_id)
            && $usuario->can('despesa.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('despesa.criar');
    }

    public function update(Usuario $usuario, Despesa $despesa): bool
    {
        return $usuario->rede_id === $despesa->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $despesa->empresa_id)
            && $usuario->can('despesa.editar');
    }

    public function delete(Usuario $usuario, Despesa $despesa): bool
    {
        return $usuario->rede_id === $despesa->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $despesa->empresa_id)
            && $usuario->can('despesa.excluir');
    }
}
