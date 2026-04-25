<?php

namespace App\Modules\Pagamento\Policies;

use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Usuario\Models\Usuario;

class PagamentoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('pagamento.ver');
    }

    public function view(Usuario $usuario, Pagamento $pagamento): bool
    {
        return $usuario->rede_id === $pagamento->rede_id
            && $usuario->podeAcessarEmpresa($pagamento->empresa_id)
            && $usuario->can('pagamento.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('pagamento.criar');
    }

    public function update(Usuario $usuario, Pagamento $pagamento): bool
    {
        return $usuario->rede_id === $pagamento->rede_id
            && $usuario->podeAcessarEmpresa($pagamento->empresa_id)
            && $usuario->can('pagamento.editar');
    }

    public function delete(Usuario $usuario, Pagamento $pagamento): bool
    {
        return $usuario->rede_id === $pagamento->rede_id
            && $usuario->podeAcessarEmpresa($pagamento->empresa_id)
            && $usuario->can('pagamento.excluir');
    }
}
