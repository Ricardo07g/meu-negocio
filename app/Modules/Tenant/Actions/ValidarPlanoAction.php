<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Actions;

use App\Exceptions\PlanoLimiteException;
use App\Modules\Tenant\Models\Empresa;

/**
 * Valida limites e feature flags da licenca de UMA empresa.
 *
 * Nao existe mais o recurso `empresa` (o plano nao concede unidades) nem o
 * `0 = ilimitado`: todo limite e finito.
 */
class ValidarPlanoAction
{
    public function executar(Empresa $empresa, string $recurso): void
    {
        $plano = $empresa->plano;

        match ($recurso) {
            'usuario' => $this->validarLimite(
                $empresa->contarUsuarios(),
                $plano->max_usuarios,
                'usuários'
            ),
            'estoque' => $this->validarFeature($plano->tem_estoque, 'estoque'),
            'financeiro' => $this->validarFeature($plano->tem_financeiro, 'financeiro'),
            default => null,
        };
    }

    private function validarLimite(int $atual, int $maximo, string $recurso): void
    {
        if ($atual >= $maximo) {
            throw new PlanoLimiteException($recurso);
        }
    }

    private function validarFeature(bool $habilitado, string $recurso): void
    {
        if (! $habilitado) {
            throw new PlanoLimiteException($recurso);
        }
    }
}
