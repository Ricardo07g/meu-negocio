<?php

declare(strict_types=1);

namespace App\Modules\Produto\Policies;

use App\Modules\Produto\Models\CategoriaProduto;
use App\Modules\Usuario\Models\Usuario;

class CategoriaProdutoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('produto.ver');
    }

    public function view(Usuario $usuario, CategoriaProduto $categoria): bool
    {
        return $usuario->rede_id === $categoria->rede_id && $usuario->can('produto.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('produto.criar');
    }

    public function update(Usuario $usuario, CategoriaProduto $categoria): bool
    {
        return $usuario->rede_id === $categoria->rede_id && $usuario->can('produto.editar');
    }

    public function delete(Usuario $usuario, CategoriaProduto $categoria): bool
    {
        return $usuario->rede_id === $categoria->rede_id && $usuario->can('produto.excluir');
    }
}
