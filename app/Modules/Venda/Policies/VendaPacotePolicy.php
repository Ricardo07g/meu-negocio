<?php

namespace App\Modules\Venda\Policies;

use App\Modules\Usuario\Models\Usuario;
use App\Modules\Venda\Models\VendaPacote;

class VendaPacotePolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('agendamento.ver');
    }

    public function view(Usuario $usuario, VendaPacote $venda): bool
    {
        return $usuario->rede_id === $venda->rede_id
            && $usuario->podeAcessarEmpresa($venda->empresa_id)
            && $usuario->can('agendamento.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('agendamento.criar');
    }

    public function cancel(Usuario $usuario, VendaPacote $venda): bool
    {
        return $usuario->rede_id === $venda->rede_id
            && $usuario->podeAcessarEmpresa($venda->empresa_id)
            && $usuario->can('agendamento.cancelar');
    }
}
