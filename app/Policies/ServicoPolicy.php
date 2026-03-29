<?php

namespace App\Policies;

use App\Models\Servico;
use App\Models\Usuario;

class ServicoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('servico.ver');
    }

    public function view(Usuario $usuario, Servico $servico): bool
    {
        return $usuario->rede_id === $servico->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $servico->empresa_id)
            && $usuario->can('servico.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('servico.criar');
    }

    public function update(Usuario $usuario, Servico $servico): bool
    {
        return $usuario->rede_id === $servico->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $servico->empresa_id)
            && $usuario->can('servico.editar');
    }

    public function delete(Usuario $usuario, Servico $servico): bool
    {
        return $usuario->rede_id === $servico->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $servico->empresa_id)
            && $usuario->can('servico.excluir');
    }
}
