<?php

namespace App\Policies;

use App\Models\Rede;
use App\Models\Usuario;

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
