<?php

declare(strict_types=1);

namespace App\Modules\Venda\Policies;

use App\Modules\Usuario\Models\Usuario;
use App\Modules\Venda\Models\VendaProduto;

class VendaProdutoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('agendamento.ver');
    }

    public function view(Usuario $usuario, VendaProduto $venda): bool
    {
        return $usuario->rede_id === $venda->rede_id
            && $usuario->podeAcessarEmpresa($venda->empresa_id)
            && $usuario->can('agendamento.ver');
    }

    public function cancel(Usuario $usuario, VendaProduto $venda): bool
    {
        return $usuario->rede_id === $venda->rede_id
            && $usuario->podeAcessarEmpresa($venda->empresa_id)
            && $usuario->can('agendamento.cancelar');
    }

    public function delete(Usuario $usuario, VendaProduto $venda): bool
    {
        return $usuario->rede_id === $venda->rede_id
            && $usuario->podeAcessarEmpresa($venda->empresa_id)
            && $usuario->can('agendamento.excluir');
    }
}
