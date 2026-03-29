<?php

namespace App\Policies;

use App\Models\Plano;
use App\Models\Usuario;

class PlanoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('plano.ver');
    }

    public function alterar(Usuario $usuario): bool
    {
        return $usuario->can('plano.alterar');
    }
}
