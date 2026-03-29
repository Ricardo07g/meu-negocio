<?php

namespace App\Policies;

use App\Models\Produto;
use App\Models\Usuario;

class ProdutoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('produto.ver');
    }

    public function view(Usuario $usuario, Produto $produto): bool
    {
        return $usuario->rede_id === $produto->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $produto->empresa_id)
            && $usuario->can('produto.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('produto.criar');
    }

    public function update(Usuario $usuario, Produto $produto): bool
    {
        return $usuario->rede_id === $produto->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $produto->empresa_id)
            && $usuario->can('produto.editar');
    }

    public function delete(Usuario $usuario, Produto $produto): bool
    {
        return $usuario->rede_id === $produto->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $produto->empresa_id)
            && $usuario->can('produto.excluir');
    }
}
