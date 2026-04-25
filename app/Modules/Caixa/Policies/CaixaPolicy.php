<?php

namespace App\Modules\Caixa\Policies;

use App\Modules\Caixa\Models\Caixa;
use App\Modules\Usuario\Models\Usuario;

class CaixaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('financeiro.ver');
    }

    public function view(Usuario $usuario, Caixa $caixa): bool
    {
        return $usuario->rede_id === $caixa->rede_id
            && $usuario->podeAcessarEmpresa($caixa->empresa_id)
            && $usuario->can('financeiro.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('financeiro.criar');
    }

    public function update(Usuario $usuario, Caixa $caixa): bool
    {
        return $usuario->rede_id === $caixa->rede_id
            && $usuario->podeAcessarEmpresa($caixa->empresa_id)
            && $usuario->can('financeiro.editar');
    }

    public function delete(Usuario $usuario, Caixa $caixa): bool
    {
        return $usuario->rede_id === $caixa->rede_id
            && $usuario->podeAcessarEmpresa($caixa->empresa_id)
            && $usuario->can('financeiro.editar');
    }
}
