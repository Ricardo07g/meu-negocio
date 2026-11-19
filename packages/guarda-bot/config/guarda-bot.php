<?php

declare(strict_types=1);

use Ricardo07g\GuardaBot\Detectores\{AgenteDeUsuario, Armadilha, CabecalhosDeNavegador};

return [

    /*
    |--------------------------------------------------------------------------
    | Modo de operacao
    |--------------------------------------------------------------------------
    |
    | desligado  o middleware `guarda-bot` passa direto, nada e avaliado.
    | observar   avalia e registra no log, mas nunca bloqueia. Use no primeiro
    |            deploy para medir falso positivo antes de ligar o bloqueio.
    | ativo      bloqueia quando o score atinge o `limiar_bloqueio`.
    |
    | Valor desconhecido cai em `observar`: um erro de digitacao nao pode
    | trancar usuarios reais do lado de fora.
    |
    */

    'modo' => env('GUARDA_BOT_MODO', 'observar'),

    /*
    |--------------------------------------------------------------------------
    | Limiar de bloqueio (0 a 100)
    |--------------------------------------------------------------------------
    |
    | O score de uma requisicao e a soma dos pesos dos sinais encontrados, com
    | teto de 100. A partir do limiar, o modo `ativo` bloqueia.
    |
    */

    'limiar_bloqueio' => (int) env('GUARDA_BOT_LIMIAR', 70),

    /*
    |--------------------------------------------------------------------------
    | Detectores
    |--------------------------------------------------------------------------
    |
    | Classes que implementam Ricardo07g\GuardaBot\Contratos\Detector. Para
    | acrescentar um detector proprio, publique esta config e inclua a classe.
    |
    */

    'detectores' => [
        AgenteDeUsuario::class,
        CabecalhosDeNavegador::class,
        Armadilha::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pesos dos sinais
    |--------------------------------------------------------------------------
    |
    | Cada detector emite sinais por motivo; o peso de cada motivo mora aqui
    | para poder ser ajustado sem mexer em codigo. Um detector proprio pode
    | sugerir o peso no proprio sinal; o valor desta tabela, quando existe,
    | prevalece.
    |
    | Regra de desenho: sinais que um humano pode produzir sem querer (autofill
    | rapido, navegador antigo sem Sec-Fetch) ficam ABAIXO do limiar e so
    | bloqueiam somados a outros.
    |
    */

    'pesos' => [
        // User-Agent
        'automacao_declarada' => 100,  // HeadlessChrome, curl, python-requests, PhantomJS...
        'agente_ia_declarado' => 100,  // ChatGPT-User, Claude-User, Perplexity-User, Signature-Agent...
        'crawler' => 70,               // robo conhecido (lista do crawler-detect)
        'sem_user_agent' => 70,

        // Coerencia de cabecalhos de um "navegador"
        'sem_accept_language' => 30,
        'sem_sec_fetch' => 30,
        'client_hint_headless' => 100,

        // Armadilha (honeypot + tempo minimo)
        'armadilha_preenchida' => 100,
        'armadilha_ausente' => 40,
        'armadilha_invalida' => 40,
        'envio_rapido' => 40,
    ],

    /*
    |--------------------------------------------------------------------------
    | Armadilha (honeypot)
    |--------------------------------------------------------------------------
    |
    | `campo` e um input invisivel para humanos: robo que preenche tudo cai nele.
    | `campo_tempo` carrega a hora de renderizacao do formulario, criptografada;
    | envio mais rapido que `tempo_minimo_ms` vira sinal (nunca bloqueia sozinho:
    | gerenciador de senha preenche o login em menos de um segundo).
    |
    */

    'armadilha' => [
        'campo' => 'website',
        'campo_tempo' => 'guarda_bot_t',
        'tempo_minimo_ms' => (int) env('GUARDA_BOT_TEMPO_MINIMO_MS', 1500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile
    |--------------------------------------------------------------------------
    |
    | Desafio do middleware `guarda-bot.turnstile` e do componente
    | <x-guarda-bot::turnstile />. Sem as duas chaves o recurso fica DESLIGADO:
    | o widget nao renderiza e a verificacao passa direto (dev, CI e testes
    | rodam sem chave e sem rede). Nao exige o dominio na Cloudflare.
    |
    | Se a Cloudflare nao responder, a requisicao e LIBERADA: derrubar o login de
    | todo mundo porque a verificacao piscou seria pior que o bot que ela evita.
    |
    */

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
        'timeout' => 5,
        'idioma' => 'pt-br',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resposta ao bloqueio e registro
    |--------------------------------------------------------------------------
    */

    'mensagem' => 'Não conseguimos confirmar que este acesso é humano. Tente novamente.',

    // Canal de log dos vereditos com sinal. null = canal padrao da aplicacao.
    'log_canal' => env('GUARDA_BOT_LOG_CANAL'),

];
