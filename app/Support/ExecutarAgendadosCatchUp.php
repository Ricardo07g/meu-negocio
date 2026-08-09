<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Artisan, Cache, Log};
use Throwable;

/**
 * Executa as tarefas de config('cron.tarefas') que ficaram devidas desde o
 * ultimo tick — o motor por tras do endpoint POST /cron/executar.
 *
 * Por que nao chamar `schedule:run`: ele so dispara a tarefa se o MINUTO atual
 * casar com a expressao cron, ou seja, assume um tick a cada minuto. Em
 * producao o tick vem de um Cron Trigger do Cloudflare que roda poucas vezes
 * por dia (pingar a cada minuto manteria o container do Railway acordado 24/7 e
 * acabaria com a economia do App Sleeping), e um trigger atrasado em alguns
 * segundos ja perderia a janela.
 *
 * Aqui a pergunta e outra: "alguma execucao ficou devida no intervalo
 * (ultimo tick, agora]?". Isso desacopla a frequencia do ping da frequencia
 * declarada na tarefa — as tres tarefas de hoje sao varreduras idempotentes
 * ("apague o que passou do prazo"), entao rodar em lote atrasado equivale a
 * rodar na hora.
 *
 * Os comandos rodam IN-PROCESS (Artisan::call), nao como subprocesso — e o que
 * `Event::run()` do scheduler faria. Poupa um bootstrap de PHP por tarefa (o
 * `artisan serve` do Railway atende um request por vez) e deixa o fluxo
 * testavel de ponta a ponta com o SQLite in-memory da suite.
 */
class ExecutarAgendadosCatchUp
{
    public const CHAVE_ULTIMO_TICK = 'cron.ultimo_tick';

    /**
     * @return array{ultimo_tick: string, tick: string, executados: list<array<string, mixed>>}
     */
    public function executar(): array
    {
        $agora = Carbon::now();
        $ultimo = $this->ultimoTick($agora);

        $executados = [];

        foreach ((array) config('cron.tarefas', []) as $comando => $expressao) {
            if ($this->ficouDevido((string) $expressao, $ultimo, $agora)) {
                $executados[] = $this->rodar((string) $comando);
            }
        }

        // Avanca o tick mesmo se alguma tarefa falhou: sao varreduras cumulativas,
        // o proximo ciclo pega o que sobrou. Nao avancar arriscaria repetir o lote
        // inteiro indefinidamente por causa de uma tarefa quebrada.
        Cache::forever(self::CHAVE_ULTIMO_TICK, $agora->toIso8601String());

        return [
            'ultimo_tick' => $ultimo->toIso8601String(),
            'tick' => $agora->toIso8601String(),
            'executados' => $executados,
        ];
    }

    /**
     * Instante do ultimo tick, limitado a janela de recuperacao — um valor
     * ausente (primeiro ping, cache limpo) ou absurdamente antigo nao deve
     * fazer o catch-up varrer um historico que nao existe.
     */
    private function ultimoTick(CarbonInterface $agora): CarbonInterface
    {
        $janela = $agora->copy()->subHours((int) config('cron.janela_horas', 24));
        $armazenado = Cache::get(self::CHAVE_ULTIMO_TICK);

        if (! is_string($armazenado)) {
            return $janela;
        }

        try {
            $tick = Carbon::parse($armazenado);
        } catch (Throwable) {
            return $janela;
        }

        return $tick->lessThan($janela) ? $janela : $tick;
    }

    /**
     * A tarefa ficou devida no intervalo (ultimo, agora]?
     *
     * `getNextRunDate(..., allowCurrentDate: false)` devolve a proxima execucao
     * ESTRITAMENTE depois do ultimo tick — sem isso, um tick que caiu em cima do
     * horario da tarefa a re-executaria no ping seguinte.
     */
    private function ficouDevido(string $expressao, CarbonInterface $ultimo, CarbonInterface $agora): bool
    {
        return (new CronExpression($expressao))->getNextRunDate($ultimo, 0, false) <= $agora;
    }

    /**
     * @return array<string, mixed>
     */
    private function rodar(string $comando): array
    {
        $inicio = microtime(true);

        try {
            $codigo = Artisan::call($comando);

            $resultado = [
                'comando' => $comando,
                'codigo' => $codigo,
                'saida' => trim(Artisan::output()),
            ];
        } catch (Throwable $e) {
            Log::error("Agendador HTTP: falha ao rodar {$comando}.", ['exception' => $e]);

            $resultado = [
                'comando' => $comando,
                'codigo' => 1,
                'erro' => $e->getMessage(),
            ];
        }

        $resultado['ms'] = (int) round((microtime(true) - $inicio) * 1000);

        return $resultado;
    }
}
