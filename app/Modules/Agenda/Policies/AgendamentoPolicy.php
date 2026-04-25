<?php

namespace App\Modules\Agenda\Policies;

use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Usuario\Models\Usuario;

class AgendamentoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('agendamento.ver');
    }

    public function view(Usuario $usuario, Agendamento $agendamento): bool
    {
        return $usuario->rede_id === $agendamento->rede_id
            && $usuario->podeAcessarEmpresa($agendamento->empresa_id)
            && $usuario->can('agendamento.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('agendamento.criar');
    }

    public function update(Usuario $usuario, Agendamento $agendamento): bool
    {
        return $usuario->rede_id === $agendamento->rede_id
            && $usuario->podeAcessarEmpresa($agendamento->empresa_id)
            && $usuario->can('agendamento.editar');
    }

    public function cancel(Usuario $usuario, Agendamento $agendamento): bool
    {
        return $usuario->rede_id === $agendamento->rede_id
            && $usuario->podeAcessarEmpresa($agendamento->empresa_id)
            && $usuario->can('agendamento.cancelar');
    }

    public function delete(Usuario $usuario, Agendamento $agendamento): bool
    {
        return $usuario->rede_id === $agendamento->rede_id
            && $usuario->podeAcessarEmpresa($agendamento->empresa_id)
            && $usuario->can('agendamento.excluir');
    }
}
