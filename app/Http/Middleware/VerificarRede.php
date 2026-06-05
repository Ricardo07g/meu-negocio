<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\StatusRede;
use App\Exceptions\TenantNaoEncontradoException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarRede
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario || ! $usuario->rede_id) {
            throw new TenantNaoEncontradoException;
        }

        $rede = $usuario->rede;

        if (! $rede || $rede->status !== StatusRede::Ativa) {
            throw new TenantNaoEncontradoException;
        }

        return $next($request);
    }
}
