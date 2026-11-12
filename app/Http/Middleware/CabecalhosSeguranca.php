<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\PoliticaCsp;
use Closure;
use Illuminate\Http\{Request, Response as IlluminateResponse};
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabecalhos de seguranca em toda resposta do Laravel (ADR-0023).
 *
 * Registrado com `prepend` no middleware global, de proposito: fica por fora ate do
 * `ValidatePathEncoding`, entao a resposta de erro de uma URL malformada tambem sai
 * protegida. Os estaticos (CSS/JS/imagem) nao passam por aqui — o Caddy (producao)
 * e o nginx (dev) cuidam do `nosniff` deles.
 */
class CabecalhosSeguranca
{
    /**
     * Valem para qualquer resposta, HTML ou nao.
     *
     * - nosniff: o navegador respeita o Content-Type em vez de "adivinhar".
     * - SAMEORIGIN (nao DENY): o modal de recibo embute o PDF da propria app.
     * - Permissions-Policy: o sistema nao usa camera, microfone, localizacao,
     *   pagamento nem USB — desligar fecha a porta para script de terceiro.
     * - COOP/CORP same-origin: nenhuma outra origem abre janela com acesso a esta
     *   nem embute os recursos dela.
     *
     * Fora de proposito: Cross-Origin-Embedder-Policy. `require-corp` so serve para
     * isolamento (SharedArrayBuffer) e quebraria imagens do R2, Turnstile e Google Fonts.
     */
    private const FIXOS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        'X-Permitted-Cross-Domain-Policies' => 'none',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Antes do $next: a view renderiza dentro dele e o @nonce/@vite precisam do valor.
        $nonce = PoliticaCsp::modo() !== PoliticaCsp::MODO_DESLIGADO ? Vite::useCspNonce() : null;

        /** @var Response $response */
        $response = $next($request);

        foreach (self::FIXOS as $nome => $valor) {
            $response->headers->set($nome, $valor);
        }

        // Atras do proxy do Railway, isSecure() vem do X-Forwarded-Proto (trustProxies).
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.(int) config('seguranca.hsts_max_age'));
        }

        if ($nonce !== null && $this->ehDocumentoHtml($response) && ! $this->ehTelaDeDebug($response)) {
            $response->headers->set(PoliticaCsp::cabecalho(), PoliticaCsp::montar($nonce));
        }

        return $response;
    }

    /**
     * CSP so em documento HTML. Em PDF ela nao protege nada e atrapalha: o
     * visualizador de PDF do Chrome roda dentro do documento e herda a politica.
     * Sem Content-Type ainda, o Symfony vai assumir text/html no envio.
     */
    private function ehDocumentoHtml(Response $response): bool
    {
        $tipo = $response->headers->get('Content-Type');

        return $tipo === null || str_contains(strtolower($tipo), 'text/html');
    }

    /**
     * A tela de excecao do Laravel (so com debug, so em dev — GuardaDeDebug) usa
     * script inline sem nonce. Com CSP ela perderia a navegacao entre abas.
     */
    private function ehTelaDeDebug(Response $response): bool
    {
        return (bool) config('app.debug')
            && $response instanceof IlluminateResponse
            && $response->exception !== null;
    }
}
