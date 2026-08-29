<?php

declare(strict_types=1);

namespace App\Modules\Ia\DTOs;

use Spatie\LaravelData\Data;

/**
 * Resposta do provedor, ja normalizada. O resto do sistema conhece SO este DTO —
 * e por isso que trocar de modelo nao vaza para tela, service ou teste.
 */
class RespostaIa extends Data
{
    public function __construct(
        /** Conteudo estruturado, aderente ao schema pedido. */
        public array $dados,
        public int $tokensEntrada,
        public int $tokensSaida,
        public string $modelo,
        public int $duracaoMs,
    ) {}

    public function tokensTotais(): int
    {
        return $this->tokensEntrada + $this->tokensSaida;
    }

    /** Custo estimado em USD, a partir dos precos do driver ativo. */
    public function custoEstimado(float $precoEntrada, float $precoSaida): float
    {
        return ($this->tokensEntrada / 1_000_000) * $precoEntrada
            + ($this->tokensSaida / 1_000_000) * $precoSaida;
    }
}
