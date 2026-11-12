<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cabecalhos de seguranca HTTP e Content-Security-Policy (ADR-0023)
|--------------------------------------------------------------------------
|
| Aplicados pelo middleware App\Http\Middleware\CabecalhosSeguranca em toda
| resposta do Laravel. A politica e montada por App\Support\PoliticaCsp.
|
*/

return [

    'csp' => [

        /*
         | ativo     -> Content-Security-Policy: o navegador bloqueia o que violar.
         | relatorio -> Content-Security-Policy-Report-Only: so avisa no console.
         |              Use no primeiro deploy de uma mudanca de politica, para
         |              ver o que quebraria antes de quebrar.
         | desligado -> sem CSP (os demais cabecalhos continuam).
         */
        'modo' => env('CSP_MODO', 'ativo'),

        /*
         | Origens externas por diretiva, ALEM de 'self'. Servico novo no navegador
         | (widget, CDN, API chamada por fetch) entra aqui — sem isso, o navegador
         | bloqueia. As origens de arquivo (R2/storage) e do Vite em dev sao
         | resolvidas em runtime pela PoliticaCsp, nao listadas aqui.
         */
        'origens' => [
            // Turnstile (anti-bot do login/registro): script + o iframe do desafio.
            'script-src' => ['https://challenges.cloudflare.com'],
            'frame-src' => ['https://challenges.cloudflare.com'],

            // Google Fonts: a landing e o @import do theme.min.css do Duralux.
            'style-src' => ['https://fonts.googleapis.com'],
            'font-src' => ['https://fonts.gstatic.com'],

            // Autopreenchimento de endereco pelo CEP (layouts/app.blade.php).
            'connect-src' => ['https://viacep.com.br'],

            'img-src' => [],
        ],
    ],

    /*
     | Strict-Transport-Security, so em requisicao HTTPS. Sem includeSubDomains
     | nem preload: o dominio pai (up.railway.app) nao e nosso.
     */
    'hsts_max_age' => (int) env('HSTS_MAX_AGE', 31536000),

];
