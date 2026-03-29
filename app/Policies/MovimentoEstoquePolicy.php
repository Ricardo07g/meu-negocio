<?php

namespace App\Policies;

use App\Models\MovimentoEstoque;
use App\Models\Usuario;

class MovimentoEstoquePolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('movimento_estoque.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('movimento_estoque.criar');
    }
}
