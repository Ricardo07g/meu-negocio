<?php

namespace App\Modules\Tenant\Policies;

use App\Modules\Tenant\Models\Plano;
use App\Modules\Usuario\Models\Usuario;

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
