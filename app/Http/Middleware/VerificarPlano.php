<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\PlanoLimiteException;
use App\Support\PlanoVigente;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarPlano
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        // A licenca e da empresa em contexto, nao da rede: duas unidades da mesma
        // rede podem estar em planos diferentes.
        $plano = PlanoVigente::resolver();

        if ($plano === null) {
            throw new PlanoLimiteException($feature);
        }

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
