<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Ricardo07g\GuardaBot\{AvaliadorDeRisco, Modo, Turnstile};
use Ricardo07g\GuardaBot\Detectores\Armadilha;

/**
 * `guarda-bot:diagnosticar`: mostra a configuracao efetiva e, com `--ua`,
 * simula o veredito de um POST de formulario vindo daquele User-Agent.
 *
 * A simulacao monta um POST "bem comportado" (cabecalhos de navegador e
 * armadilha preenchida corretamente), para que o resultado isole o efeito do
 * User-Agent informado.
 */
class DiagnosticarGuardaBot extends Command
{
    protected $signature = 'guarda-bot:diagnosticar {--ua= : User-Agent a simular}';

    protected $description = 'Mostra a configuracao do GuardaBot e simula o veredito de um User-Agent';

    public function handle(AvaliadorDeRisco $avaliador, Armadilha $armadilha): int
    {
        $this->components->twoColumnDetail('Modo', Modo::atual()->value);
        $this->components->twoColumnDetail('Limiar de bloqueio', (string) config('guarda-bot.limiar_bloqueio'));
        $this->components->twoColumnDetail('Turnstile', Turnstile::estaAtivo() ? 'ativo' : 'desligado (sem chaves)');

        foreach ((array) config('guarda-bot.detectores', []) as $detector) {
            $this->components->twoColumnDetail('Detector', class_basename((string) $detector));
        }

        $agente = $this->option('ua');

        if (! is_string($agente) || $agente === '') {
            return self::SUCCESS;
        }

        $veredito = $avaliador->avaliar(Request::create('/', 'POST', [
            (string) config('guarda-bot.armadilha.campo') => '',
            (string) config('guarda-bot.armadilha.campo_tempo') => $armadilha->carimbo(now()->subMinute()->getTimestampMs()),
        ], server: [
            'HTTP_USER_AGENT' => $agente,
            'HTTP_ACCEPT_LANGUAGE' => 'pt-BR,pt;q=0.9',
            'HTTP_SEC_FETCH_SITE' => 'same-origin',
            'HTTP_SEC_FETCH_MODE' => 'navigate',
            'HTTPS' => 'on',
        ]));

        $this->newLine();
        $this->components->twoColumnDetail('Score', $veredito->score.' / limiar '.$veredito->limiar);

        foreach ($veredito->motivos as $motivo => $peso) {
            $this->components->twoColumnDetail('  '.$motivo, '+'.$peso);
        }

        $this->components->twoColumnDetail('Decisao no modo ativo', $veredito->deveBloquear() ? '<fg=red>bloquear</>' : '<fg=green>permitir</>');

        return self::SUCCESS;
    }
}
