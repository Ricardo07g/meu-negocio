<?php

declare(strict_types=1);

namespace App\Modules\Cliente\Policies;

use App\Modules\Cliente\Models\Cliente;
use App\Modules\Usuario\Models\Usuario;

class ClientePolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('cliente.ver');
    }

    public function view(Usuario $usuario, Cliente $cliente): bool
    {
        return $usuario->rede_id === $cliente->rede_id
            && $usuario->can('cliente.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('cliente.criar');
    }

    public function update(Usuario $usuario, Cliente $cliente): bool
    {
        return $usuario->rede_id === $cliente->rede_id
            && $usuario->can('cliente.editar');
    }

    public function delete(Usuario $usuario, Cliente $cliente): bool
    {
        return $usuario->rede_id === $cliente->rede_id
            && $usuario->can('cliente.excluir');
    }
}
