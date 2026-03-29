<?php

namespace App\Policies;

use App\Models\Pagamento;
use App\Models\Usuario;

class PagamentoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('pagamento.ver');
    }

    public function view(Usuario $usuario, Pagamento $pagamento): bool
    {
        return $usuario->rede_id === $pagamento->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $pagamento->empresa_id)
            && $usuario->can('pagamento.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('pagamento.criar');
    }

    public function update(Usuario $usuario, Pagamento $pagamento): bool
    {
        return $usuario->rede_id === $pagamento->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $pagamento->empresa_id)
            && $usuario->can('pagamento.editar');
    }

    public function delete(Usuario $usuario, Pagamento $pagamento): bool
    {
        return $usuario->rede_id === $pagamento->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $pagamento->empresa_id)
            && $usuario->can('pagamento.excluir');
    }
}
