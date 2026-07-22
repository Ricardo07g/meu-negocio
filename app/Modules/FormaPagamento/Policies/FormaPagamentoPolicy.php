<?php

declare(strict_types=1);

namespace App\Modules\FormaPagamento\Policies;

use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\Usuario\Models\Usuario;

class FormaPagamentoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('forma_pagamento.ver');
    }

    public function view(Usuario $usuario, FormaPagamento $forma): bool
    {
        return $this->mesmaRedeEEmpresa($usuario, $forma) && $usuario->can('forma_pagamento.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('forma_pagamento.criar');
    }

    public function update(Usuario $usuario, FormaPagamento $forma): bool
    {
        return $this->mesmaRedeEEmpresa($usuario, $forma) && $usuario->can('forma_pagamento.editar');
    }

    public function delete(Usuario $usuario, FormaPagamento $forma): bool
    {
        return $this->mesmaRedeEEmpresa($usuario, $forma) && $usuario->can('forma_pagamento.excluir');
    }

    private function mesmaRedeEEmpresa(Usuario $usuario, FormaPagamento $forma): bool
    {
        return $usuario->rede_id === $forma->rede_id
            && $usuario->podeAcessarEmpresa($forma->empresa_id);
    }
}
