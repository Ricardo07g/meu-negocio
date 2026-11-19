<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot;

/**
 * Resultado da avaliacao de uma requisicao: score de 0 a 100 e os motivos
 * (motivo => peso aplicado) que o compuseram.
 */
final readonly class Veredito
{
    /**
     * @param  array<string, int>  $motivos
     */
    public function __construct(
        public int $score,
        public array $motivos,
        public int $limiar,
    ) {}

    public function deveBloquear(): bool
    {
        return $this->score >= $this->limiar;
    }

    public function limpo(): bool
    {
        return $this->motivos === [];
    }
}
