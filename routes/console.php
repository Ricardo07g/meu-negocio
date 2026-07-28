<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\{Artisan, Schedule};

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Limpa rascunhos de upload (tmp) abandonados no bucket.
Schedule::command('arquivos:limpar-rascunhos')->daily();

// Remove exportacoes de extrato expiradas (arquivo + registro), de hora em hora.
Schedule::command('exportacoes:limpar')->hourly();

// Encerra os testes gratuitos vencidos (unidade cai do Pro para o Gratis).
Schedule::command('assinaturas:expirar-trial')->daily();
