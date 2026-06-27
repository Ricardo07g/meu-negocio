<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Policies;

use App\Modules\Usuario\Models\Usuario;

class FaturaPolicy
{
    /**
     * Qualquer usuario autenticado ve a assinatura da PROPRIA rede — o
     * isolamento por rede_id e garantido pelo RedeTrait (global scope).
     */
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    /**
     * Trocar de plano e uma decisao de cobranca da rede: restrita ao Admin.
     */
    public function transicionar(Usuario $usuario): bool
    {
        return $usuario->hasRole('Admin');
    }

    /**
     * Marcar uma fatura como paga e tambem uma decisao de cobranca: so o Admin.
     */
    public function pagar(Usuario $usuario): bool
    {
        return $usuario->hasRole('Admin');
    }
}
