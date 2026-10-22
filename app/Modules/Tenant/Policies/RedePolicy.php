<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Policies;

use App\Modules\Tenant\Models\Rede;
use App\Modules\Usuario\Models\Usuario;

class RedePolicy
{
    /**
     * Politica de seguranca da rede (exigir 2FA — ADR-0022): decisao do dono,
     * mesma alcada da assinatura (FaturaPolicy). A comparacao de rede_id e
     * explicita porque Rede nao tem global scope (e o proprio tenant).
     */
    public function configurarSeguranca(Usuario $usuario, Rede $rede): bool
    {
        return $usuario->rede_id === $rede->id && $usuario->hasRole('Admin');
    }
}
