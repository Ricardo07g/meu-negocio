<?php

namespace App\Policies;

use App\Models\Usuario;
use App\Models\VendaPacote;

class VendaPacotePolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('agendamento.ver');
    }

    public function view(Usuario $usuario, VendaPacote $venda): bool
    {
        return $usuario->rede_id === $venda->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $venda->empresa_id)
            && $usuario->can('agendamento.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('agendamento.criar');
    }

    public function cancel(Usuario $usuario, VendaPacote $venda): bool
    {
        return $usuario->rede_id === $venda->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $venda->empresa_id)
            && $usuario->can('agendamento.cancelar');
    }
}
