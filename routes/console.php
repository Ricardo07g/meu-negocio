<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\{Artisan, Schedule};

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Agendamento: a lista mora em config/cron.php (comando => expressao cron) porque
// tem dois consumidores — o `schedule:work` do docker-compose, aqui, e o endpoint
// POST /cron/executar acionado pelo Cron Trigger do Cloudflare em producao, onde
// nao ha processo de scheduler. Ver docs/ADR/0016.
foreach ((array) config('cron.tarefas', []) as $comando => $expressao) {
    Schedule::command((string) $comando)->cron((string) $expressao);
}
