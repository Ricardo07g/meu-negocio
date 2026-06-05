<?php

declare(strict_types=1);

namespace App\Modules\Despesa\Policies;

use App\Modules\Despesa\Models\CategoriaDespesa;
use App\Modules\Usuario\Models\Usuario;

class CategoriaDespesaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('categoria_despesa.ver');
    }

    public function view(Usuario $usuario, CategoriaDespesa $categoria): bool
    {
        return $usuario->rede_id === $categoria->rede_id && $usuario->can('categoria_despesa.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('categoria_despesa.criar');
    }

    public function update(Usuario $usuario, CategoriaDespesa $categoria): bool
    {
        return $usuario->rede_id === $categoria->rede_id && $usuario->can('categoria_despesa.editar');
    }

    public function delete(Usuario $usuario, CategoriaDespesa $categoria): bool
    {
        return $usuario->rede_id === $categoria->rede_id && $usuario->can('categoria_despesa.excluir');
    }
}
