<?php

declare(strict_types=1);

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

    /**
     * Encaixar fora do expediente.
     *
     * Separado de `criar` de proposito: recepcao agenda, mas quem decide abrir
     * a loja fora do horario e quem responde pela unidade.
     */
    public function forcarHorario(Usuario $usuario): bool
    {
        return $usuario->can('agendamento.forcar_horario');
    }

    public function delete(Usuario $usuario, Agendamento $agendamento): bool
    {
        return $usuario->rede_id === $agendamento->rede_id
            && $usuario->podeAcessarEmpresa($agendamento->empresa_id)
            && $usuario->can('agendamento.excluir');
    }
}
