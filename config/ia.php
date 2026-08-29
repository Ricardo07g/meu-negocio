<?php

declare(strict_types=1);

/**
 * Analise por IA — configuracao do provedor.
 *
 * **Desligada quando nao configurada.** Sem credencial do driver ativo o
 * `Ia::estaAtivo()` responde false, nada e chamado e a tela degrada com aviso —
 * dev, CI e a suite rodam sem chave e sem tocar a rede (mesma politica do Turnstile).
 *
 * Os precos existem so para estimar custo no registro de consumo; nao sao cobrados
 * por aqui nem precisam ser exatos ao centavo.
 */
return [
    'driver' => env('IA_DRIVER', 'workers_ai'),

    'timeout' => (int) env('IA_TIMEOUT', 30),

    /*
     * Fuso do "dia" da cota. O app roda em UTC (config/app.php), entao contar a cota com
     * `today()` cru a zeraria as 21h de Brasilia — no meio do expediente do lojista, que
     * nao entenderia o que aconteceu. O dia da cota e o dia DELE.
     */
    'fuso' => env('IA_FUSO', 'America/Sao_Paulo'),

    /** Rede de seguranca do cache: o hash do payload e quem invalida de verdade. */
    'cache_dias' => (int) env('IA_CACHE_DIAS', 30),

    'drivers' => [

        /*
         * Cloudflare Workers AI — driver padrao.
         *
         * Reaproveita a conta Cloudflare que o projeto ja usa (R2, Turnstile, cron):
         * `IA_CF_ACCOUNT_ID` e o mesmo valor de `R2_ACCOUNT_ID`. O token precisa da
         * permissao "Workers AI: Read".
         *
         * Suporta JSON schema via `response_format`, mas — ao contrario do Gemini — a
         * Cloudflare NAO garante aderencia a schemas complexos: quando o modelo nao
         * consegue cumprir, a API devolve erro. Mantenha o schema achatado.
         */
        'workers_ai' => [
            'account_id' => env('IA_CF_ACCOUNT_ID', env('R2_ACCOUNT_ID')),
            'token' => env('IA_CF_TOKEN'),
            /*
             * 70B por aderencia ao schema e portugues, nao por preco — custo aqui e troco:
             * ~88 neuronios por analise, e com a franquia de 10 analises/dia cabem ~11
             * unidades no limite dentro da cota gratis de 10.000 neuronios/dia da Cloudflare.
             * Alternativa ~6x mais barata se precisar de volume:
             * `@cf/meta/llama-3.1-8b-instruct-fp8-fast`.
             */
            'modelo' => env('IA_CF_MODELO', '@cf/meta/llama-3.3-70b-instruct-fp8-fast'),
            'url_base' => env('IA_CF_URL_BASE', 'https://api.cloudflare.com/client/v4'),
            // USD por 1M tokens (tabela de neuronios do Workers AI, para o 70B fp8-fast).
            // Trocou de modelo? Ajuste tambem, senao o custo estimado no historico mente.
            'precos' => [
                'entrada' => (float) env('IA_CF_PRECO_ENTRADA', 0.293),
                'saida' => (float) env('IA_CF_PRECO_SAIDA', 2.253),
            ],
        ],

        /*
         * Google Gemini — escape hatch.
         *
         * Existe para o caso de o portugues do Llama decepcionar: o `responseSchema` do
         * Gemini e imposto pelo servidor, entao a saida estruturada e mais previsivel.
         * Trocar e uma linha: IA_DRIVER=gemini.
         */
        'gemini' => [
            'chave' => env('IA_GEMINI_CHAVE'),
            'modelo' => env('IA_GEMINI_MODELO', 'gemini-2.5-flash-lite'),
            'url_base' => env('IA_GEMINI_URL_BASE', 'https://generativelanguage.googleapis.com/v1beta'),
            'precos' => [
                'entrada' => (float) env('IA_GEMINI_PRECO_ENTRADA', 0.10),
                'saida' => (float) env('IA_GEMINI_PRECO_SAIDA', 0.40),
            ],
        ],

        /** Driver da suite: responde payload fixo, nunca toca a rede. */
        'fake' => [
            'modelo' => 'fake',
            'precos' => ['entrada' => 0.0, 'saida' => 0.0],
        ],
    ],
];
