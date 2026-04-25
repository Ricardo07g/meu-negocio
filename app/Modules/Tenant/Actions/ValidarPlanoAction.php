<?php

namespace App\Modules\Tenant\Actions;

use App\Exceptions\PlanoLimiteException;
use App\Modules\Tenant\Models\Rede;

class ValidarPlanoAction
{
    public function executar(Rede $rede, string $recurso): void
    {
        $plano = $rede->plano;

        match ($recurso) {
            'empresa' => $this->validarLimite(
                $rede->empresas()->count(),
                $plano->max_empresas,
                'empresas'
            ),
            'usuario' => $this->validarLimite(
                $rede->usuarios()->count(),
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
        // 0 = ilimitado
        if ($maximo > 0 && $atual >= $maximo) {
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
