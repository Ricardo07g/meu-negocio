<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege o endpoint do agendador HTTP (POST /cron/executar), chamado pelo
 * Cron Trigger do Cloudflare Workers.
 *
 * Nao ha usuario nem sessao aqui: a unica credencial e o segredo em
 * config('cron.token'), enviado no header X-Cron-Token.
 *
 * Duas decisoes de seguranca deliberadas:
 * - **fail-closed**: token nao configurado => 404 (ambiente sem a variavel
 *   nunca expoe o agendador, nem por engano);
 * - **404, nao 403**: uma resposta 403 confirmaria a existencia da rota para
 *   quem esta varrendo; 404 nao entrega nada.
 */
class AutenticarCron
{
    public function handle(Request $request, Closure $next): Response
    {
        $esperado = (string) config('cron.token');
        $recebido = (string) $request->header('X-Cron-Token', '');

        if ($esperado === '' || ! hash_equals($esperado, $recebido)) {
            abort(404);
        }

        return $next($request);
    }
}
