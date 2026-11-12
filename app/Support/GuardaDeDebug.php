<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;

/**
 * Debug ligado nunca vale em producao, mesmo que a variavel diga o contrario.
 *
 * Com `app.debug` ligado, qualquer excecao vira a tela de erro completa do Laravel:
 * stack trace, trecho do codigo-fonte, cabecalhos e as queries da requisicao com os
 * bindings — dado de tenant incluido. Basta uma URL malformada para disparar.
 *
 * A trava fica no codigo porque o valor vem de uma variavel de ambiente do painel de
 * deploy, que nenhum teste ve e que e facil copiar do `.env.example` (onde e `true`,
 * o certo para dev). A config e lida na hora de renderizar a excecao, entao forcar
 * aqui, no boot, vale para a requisicao inteira. O aviso de configuracao errada sai
 * uma vez por deploy no entrypoint, nao a cada request.
 */
final class GuardaDeDebug
{
    /** Retorna true quando precisou desligar o debug. */
    public static function aplicar(Application $app): bool
    {
        if (! $app->environment('production') || ! config('app.debug')) {
            return false;
        }

        config(['app.debug' => false]);

        return true;
    }
}
