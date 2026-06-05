<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Models\{Plano, Rede};
use Illuminate\Database\Eloquent\Collection;

class PlanoService
{
    public function listar(): Collection
    {
        return Plano::all();
    }

    public function buscar(int $id): Plano
    {
        return Plano::findOrFail($id);
    }

    public function verificarLimite(Rede $rede, string $recurso): bool
    {
        $plano = $rede->plano;

        return match ($recurso) {
            'empresa' => $plano->max_empresas === 0 || $rede->empresas()->count() < $plano->max_empresas,
            'usuario' => $plano->max_usuarios === 0 || $rede->usuarios()->count() < $plano->max_usuarios,
            'estoque' => $plano->tem_estoque,
            'financeiro' => $plano->tem_financeiro,
            default => true,
        };
    }
}
