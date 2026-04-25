<?php

namespace App\Http\Middleware;

use App\Exceptions\PlanoLimiteException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarPlano
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $usuario = $request->user();
        $plano = $usuario->rede->plano;

        $habilitado = match ($feature) {
            'estoque' => $plano->tem_estoque,
            'financeiro' => $plano->tem_financeiro,
            default => true,
        };

        if (! $habilitado) {
            throw new PlanoLimiteException($feature);
        }

        return $next($request);
    }
}
