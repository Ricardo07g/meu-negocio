<?php

namespace App\Http\Middleware;

use App\Exceptions\EmpresaNaoEncontradaException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarEmpresa
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // Admin pode operar sem empresa fixa
        if ($usuario->hasRole('Admin')) {
            return $next($request);
        }

        if (!$usuario->empresa_id) {
            throw new EmpresaNaoEncontradaException();
        }

        // Verificar se a empresa pertence a rede do usuario
        $empresa = $usuario->empresa;
        if (!$empresa || $empresa->rede_id !== $usuario->rede_id) {
            throw new EmpresaNaoEncontradaException();
        }

        return $next($request);
    }
}
