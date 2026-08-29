<?php

declare(strict_types=1);

namespace App\Modules\Ia\Policies;

use App\Modules\Ia\Models\AnaliseIa;
use App\Modules\Usuario\Models\Usuario;

/**
 * Analisar gasta cota da unidade — por isso e permissao propria, e nao um efeito
 * colateral de `cliente.ver`. Quem so consulta a carteira nao necessariamente pode
 * torrar o orcamento de IA do dia.
 */
class AnaliseIaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('ia.ver');
    }

    public function view(Usuario $usuario, AnaliseIa $analise): bool
    {
        return $usuario->rede_id === $analise->rede_id
            && $usuario->can('ia.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('ia.analisar');
    }
}
