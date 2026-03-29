<?php

namespace App\Modules\Servico\Policies;

use App\Modules\Servico\Models\Profissional;
use App\Modules\Usuario\Models\Usuario;

class ProfissionalPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('profissional.ver');
    }

    public function view(Usuario $usuario, Profissional $profissional): bool
    {
        return $usuario->rede_id === $profissional->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $profissional->empresa_id)
            && $usuario->can('profissional.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('profissional.criar');
    }

    public function update(Usuario $usuario, Profissional $profissional): bool
    {
        return $usuario->rede_id === $profissional->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $profissional->empresa_id)
            && $usuario->can('profissional.editar');
    }

    public function delete(Usuario $usuario, Profissional $profissional): bool
    {
        return $usuario->rede_id === $profissional->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $profissional->empresa_id)
            && $usuario->can('profissional.excluir');
    }
}
