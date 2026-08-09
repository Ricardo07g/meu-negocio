<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\{Http, Log};
use Throwable;

/**
 * Cloudflare Turnstile — anti-bot dos formularios publicos.
 *
 * O widget resolve um desafio no navegador e devolve um token no campo
 * `cf-turnstile-response`; este servico confirma o token com o Cloudflare antes
 * de a requisicao seguir.
 *
 * **Desligado quando nao configurado.** Sem `TURNSTILE_SECRET_KEY` o
 * `estaAtivo()` responde false e nada e validado — dev, CI e a suite de testes
 * rodam sem chave e sem tocar na rede.
 */
class Turnstile
{
    public const CAMPO = 'cf-turnstile-response';

    public static function estaAtivo(): bool
    {
        return filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret_key'));
    }

    /**
     * O token e valido? Erro de rede/timeout **libera** a passagem: derrubar o
     * login de todo mundo porque o Cloudflare piscou seria pior que o bot que
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
                ->timeout(5)
                ->post((string) config('services.turnstile.verify_url'), array_filter([
                    'secret' => (string) config('services.turnstile.secret_key'),
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
}
