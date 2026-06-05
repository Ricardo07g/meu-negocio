<?php

declare(strict_types=1);

namespace App\Modules\Estoque\Policies;

use App\Modules\Usuario\Models\Usuario;

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
