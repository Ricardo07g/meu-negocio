<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot;

/**
 * Um indicio de automacao encontrado por um detector.
 *
 * O `motivo` e a chave da tabela `guarda-bot.pesos`. O `peso` aqui e so uma
 * sugestao para detectores proprios: quando o motivo existe na config, a
 * config prevalece (quem opera ajusta sem tocar em codigo).
 */
final readonly class Sinal
{
    public function __construct(
        public string $motivo,
        public ?int $peso = null,
    ) {}
}
