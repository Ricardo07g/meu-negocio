<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Token do agendador HTTP
    |--------------------------------------------------------------------------
    |
    | Segredo compartilhado entre o Cron Trigger do Cloudflare Workers e o
    | endpoint POST /cron/executar. O Worker envia o valor no header
    | X-Cron-Token; o middleware AutenticarCron compara em tempo constante.
    |
    | Fail-closed: com o token vazio o endpoint responde 404 — um ambiente que
    | esqueceu de configurar a variavel nunca expoe o agendador.
    |
    */

    'token' => env('CRON_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Janela maxima de recuperacao (catch-up)
    |--------------------------------------------------------------------------
    |
    | O endpoint roda as tarefas que ficaram devidas desde o ultimo tick. Na
    | primeira execucao (ou depois de o cache ser limpo) nao ha ultimo tick:
    | assume-se este numero de horas para tras. Grande demais faria uma conta
    | recem-criada varrer um historico que nao existe; 24h cobre o intervalo
    | entre dois pings diarios com folga.
    |
    */

    'janela_horas' => (int) env('CRON_JANELA_HORAS', 24),

    /*
    |--------------------------------------------------------------------------
    | Tarefas agendadas
    |--------------------------------------------------------------------------
    |
    | Fonte unica de verdade do agendamento: comando artisan => expressao cron.
    | Dois consumidores leem daqui:
    |
    |   1. routes/console.php — registra cada tarefa no Schedule do Laravel
    |      (caminho do servico `scheduler` do docker-compose, schedule:work);
    |   2. App\Support\ExecutarAgendadosCatchUp — o endpoint HTTP acionado pelo
    |      Cron Trigger do Cloudflare em producao.
    |
    | Por isso a expressao mora aqui, e nao no acucar sintatico ->daily(): o
    | endpoint precisa da expressao para calcular o que ficou devido desde o
    | ultimo tick. O Schedule fluente continua disponivel em routes/console.php
    | para tarefas que so precisem rodar no ambiente com scheduler dedicado —
    | tarefa que precisa rodar em PRODUCAO tem de estar nesta lista.
    |
    | Horarios em UTC (config('app.timezone')).
    |
    */

    'tarefas' => [
        // Rascunhos de upload (tmp) abandonados no bucket — diario, meia-noite.
        'arquivos:limpar-rascunhos' => '0 0 * * *',

        // Exportacoes de extrato expiradas (arquivo + registro) — de hora em hora.
        'exportacoes:limpar' => '0 * * * *',

        // Testes gratuitos vencidos: a unidade cai do Pro para o Gratis — diario.
        'assinaturas:expirar-trial' => '0 0 * * *',
    ],

];
