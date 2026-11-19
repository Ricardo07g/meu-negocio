<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot;

use Illuminate\Support\Facades\{Http, Log};
use Throwable;

/**
 * Cloudflare Turnstile: o desafio do pacote.
 *
 * O widget resolve um desafio no navegador e devolve um token no campo
 * `cf-turnstile-response`; aqui o token e confirmado com a Cloudflare antes de
 * a requisicao seguir.
 *
 * **Desligado quando nao configurado.** Sem as duas chaves o `estaAtivo()`
 * responde false e nada e validado: dev, CI e testes rodam sem chave e sem
 * tocar na rede.
 */
class Turnstile
{
    public const CAMPO = 'cf-turnstile-response';

    public static function estaAtivo(): bool
    {
        return filled(config('guarda-bot.turnstile.site_key'))
            && filled(config('guarda-bot.turnstile.secret_key'));
    }

    /**
     * O token e valido? Erro de rede/timeout **libera** a passagem: derrubar o
     * login de todo mundo porque a Cloudflare piscou seria pior que o bot que
     * este widget evita.
     */
    public static function tokenValido(?string $token, ?string $ip = null): bool
    {
        if (! self::estaAtivo()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        try {
            $resposta = Http::asForm()
                ->timeout((int) config('guarda-bot.turnstile.timeout', 5))
                ->post((string) config('guarda-bot.turnstile.verify_url'), array_filter([
                    'secret' => (string) config('guarda-bot.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (Throwable $e) {
            Log::warning('Turnstile: falha ao verificar o token, liberando a requisicao.', ['exception' => $e]);

            return true;
        }

        if ($resposta->failed()) {
            Log::warning('Turnstile: verificacao respondeu erro, liberando a requisicao.', [
                'status' => $resposta->status(),
            ]);

            return true;
        }

        return (bool) $resposta->json('success', false);
    }

    /**
     * A secret key e aceita pela Cloudflare? Usado pelo instalador: um token
     * falso com a secret certa volta `invalid-input-response`; com a secret
     * errada, `invalid-input-secret`.
     *
     * @return bool|null null quando nao foi possivel perguntar (rede)
     */
    public static function secretValida(string $secret): ?bool
    {
        try {
            $resposta = Http::asForm()
                ->timeout((int) config('guarda-bot.turnstile.timeout', 5))
                ->post((string) config('guarda-bot.turnstile.verify_url'), [
                    'secret' => $secret,
                    'response' => 'guarda-bot-verificacao-de-secret',
                ]);
        } catch (Throwable) {
            return null;
        }

        if ($resposta->failed()) {
            return null;
        }

        return ! in_array('invalid-input-secret', (array) $resposta->json('error-codes', []), true);
    }
}
