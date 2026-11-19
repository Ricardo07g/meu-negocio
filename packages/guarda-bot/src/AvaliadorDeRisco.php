<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Ricardo07g\GuardaBot\Contratos\Detector;

/**
 * Roda os detectores configurados e transforma os sinais num veredito.
 *
 * Score = soma dos pesos (teto 100). Motivo repetido conta uma vez: dois
 * detectores achando a mesma coisa nao dobram a certeza.
 */
class AvaliadorDeRisco
{
    public function __construct(private readonly Container $container) {}

    public function avaliar(Request $request): Veredito
    {
        $pesos = (array) config('guarda-bot.pesos', []);
        $motivos = [];

        foreach ($this->detectores() as $detector) {
            foreach ($detector->avaliar($request) as $sinal) {
                $motivos[$sinal->motivo] = max(0, (int) ($pesos[$sinal->motivo] ?? $sinal->peso ?? 0));
            }
        }

        return new Veredito(
            score: min(100, array_sum($motivos)),
            motivos: $motivos,
            limiar: (int) config('guarda-bot.limiar_bloqueio', 70),
        );
    }

    /**
     * @return list<Detector>
     */
    private function detectores(): array
    {
        $detectores = [];

        foreach ((array) config('guarda-bot.detectores', []) as $classe) {
            $detector = $this->container->make($classe);

            if (! $detector instanceof Detector) {
                throw new InvalidArgumentException(sprintf(
                    'O detector [%s] precisa implementar %s.',
                    $classe,
                    Detector::class,
                ));
            }

            $detectores[] = $detector;
        }

        return $detectores;
    }
}
