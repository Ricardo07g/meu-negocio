<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rede com `exigir_2fa` ligado (ADR-0022): quem ainda nao configurou o 2FA so
 * alcanca o proprio perfil — onde configura — ate ativar. Roda depois do
 * `verificar.rede`, entao a rede ja existe e esta ativa.
 */
class ExigirDoisFatores
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (
            $usuario !== null
            && $usuario->rede?->exigir_2fa
            && ! $usuario->temDoisFatoresAtivo()
            && ! $request->routeIs('perfil.*')
        ) {
            return redirect()
                ->route('perfil.index', ['aba' => 'seguranca'])
                ->with('erro', 'Sua rede exige a verificação em duas etapas. Configure-a para continuar.');
        }

        return $next($request);
    }
}
