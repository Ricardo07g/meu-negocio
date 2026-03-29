<?php

namespace App\Modules\Servico\Policies;

use App\Modules\Servico\Models\Servico;
use App\Modules\Usuario\Models\Usuario;

class ServicoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('servico.ver');
    }

    public function view(Usuario $usuario, Servico $servico): bool
    {
        return $usuario->rede_id === $servico->rede_id
            && $usuario->can('servico.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('servico.criar');
    }

    public function update(Usuario $usuario, Servico $servico): bool
    {
        return $usuario->rede_id === $servico->rede_id
            && $usuario->can('servico.editar');
    }

    public function delete(Usuario $usuario, Servico $servico): bool
    {
        return $usuario->rede_id === $servico->rede_id
            && $usuario->can('servico.excluir');
    }
}
