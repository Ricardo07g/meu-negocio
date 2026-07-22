<?php

declare(strict_types=1);

namespace App\Modules\Conta\Policies;

use App\Modules\Conta\Models\Conta;
use App\Modules\Usuario\Models\Usuario;

class ContaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('conta.ver');
    }

    public function view(Usuario $usuario, Conta $conta): bool
    {
        return $this->mesmaRedeEEmpresa($usuario, $conta) && $usuario->can('conta.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('conta.criar');
    }

    public function update(Usuario $usuario, Conta $conta): bool
    {
        return $this->mesmaRedeEEmpresa($usuario, $conta) && $usuario->can('conta.editar');
    }

    public function delete(Usuario $usuario, Conta $conta): bool
    {
        return $this->mesmaRedeEEmpresa($usuario, $conta) && $usuario->can('conta.excluir');
    }

    private function mesmaRedeEEmpresa(Usuario $usuario, Conta $conta): bool
    {
        return $usuario->rede_id === $conta->rede_id
            && $usuario->podeAcessarEmpresa($conta->empresa_id);
    }
}
