<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ricardo07g\GuardaBot\Turnstile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware `guarda-bot.turnstile`: barra o POST quando o desafio nao passa.
 *
 * E middleware, e nao regra de validacao, por um motivo concreto: o Laravel so
 * roda regras sobre campos AUSENTES se elas forem implicitas, e o
 * `ImplicitRule` esta deprecado. Um bot que simplesmente nao envia o campo e o
 * caso mais comum, entao a checagem tem de acontecer fora do validador.
 *
 * Passa direto quando o Turnstile nao esta configurado.
 */
class VerificarTurnstile
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Turnstile::tokenValido($request->input(Turnstile::CAMPO), $request->ip())) {
            return $next($request);
        }

        return back()
            // Nunca reflita credencial de volta para a tela.
            ->withInput($request->except(['password', 'password_confirmation', 'current_password']))
            ->withErrors([
                Turnstile::CAMPO => 'Não foi possível confirmar que você não é um robô. Tente novamente.',
            ]);
    }
}
