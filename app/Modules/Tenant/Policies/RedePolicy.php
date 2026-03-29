<?php

namespace App\Modules\Tenant\Policies;

use App\Modules\Tenant\Models\Rede;
use App\Modules\Usuario\Models\Usuario;

class RedePolicy
{
    public function view(Usuario $usuario, Rede $rede): bool
    {
        return $usuario->rede_id === $rede->id
            && $usuario->can('rede.ver');
    }

    public function update(Usuario $usuario, Rede $rede): bool
    {
        return $usuario->rede_id === $rede->id
            && $usuario->can('rede.editar');
    }

    public function configurar(Usuario $usuario, Rede $rede): bool
    {
        return $usuario->rede_id === $rede->id
            && $usuario->can('rede.configurar');
    }
}
