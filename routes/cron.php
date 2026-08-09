<?php

declare(strict_types=1);

use App\Http\Controllers\CronController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do agendador (chamada de maquina)
|--------------------------------------------------------------------------
|
| Registradas em bootstrap/app.php pelo parametro `then:` do withRouting(),
| de proposito FORA do grupo `web`: nao ha sessao, CSRF, cookies nem os
| middlewares de tenant (verificar.rede / verificar.empresa) — nada disso faz
| sentido para um POST vindo de um Cron Trigger do Cloudflare, e o
| verificar.rede rejeitaria a requisicao por falta de usuario.
|
| A autenticacao e o header X-Cron-Token (middleware `cron.auth`). O throttle
| segura tentativa de forca bruta no token.
|
*/

Route::post('cron/executar', CronController::class)
    ->middleware(['cron.auth', 'throttle:12,1'])
    ->name('cron.executar');
