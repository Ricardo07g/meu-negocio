<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ExecutarAgendadosCatchUp;
use Illuminate\Http\JsonResponse;

/**
 * Agendador por HTTP: roda as tarefas de config('cron.tarefas') que ficaram
 * devidas desde o ultimo tick.
 *
 * Em producao (Railway, servico unico com App Sleeping ligado) nao ha processo
 * `schedule:work` — quem faz o papel de relogio e um Cron Trigger do Cloudflare
 * Workers, que chama esta rota. Ver docs/ADR/0016.
 *
 * Nao pertence a nenhum modulo: e infraestrutura transversal, como o /up.
 * A unica credencial e o header X-Cron-Token (middleware `cron.auth`).
 */
class CronController extends Controller
{
    public function __invoke(ExecutarAgendadosCatchUp $agendador): JsonResponse
    {
        return response()->json($agendador->executar());
    }
}
