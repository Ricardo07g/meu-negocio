<?php

namespace App\Support\Parcelamento;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Distribui um valor total em N parcelas com vencimentos mensais,
 * ajustando a última para absorver o arredondamento.
 */
class CalculadoraParcelas
{
    public const MIN_PARCELAS = 1;

    public const MAX_PARCELAS = 24;

    /**
     * @return array<int, array{numero:int,total:int,valor:float,data_vencimento:Carbon}>
     */
    public function calcular(float $valorTotal, int $numeroParcelas, Carbon $primeiroVencimento): array
    {
        if ($numeroParcelas < self::MIN_PARCELAS || $numeroParcelas > self::MAX_PARCELAS) {
            throw new InvalidArgumentException(
                sprintf('Número de parcelas deve estar entre %d e %d.', self::MIN_PARCELAS, self::MAX_PARCELAS)
            );
        }

        if ($valorTotal <= 0) {
            throw new InvalidArgumentException('Valor total deve ser positivo.');
        }

        $parcelas = [];
        $valorParcela = round($valorTotal / $numeroParcelas, 2);
        $valorUltima = round($valorTotal - ($valorParcela * ($numeroParcelas - 1)), 2);

        for ($i = 1; $i <= $numeroParcelas; $i++) {
            $parcelas[] = [
                'numero' => $i,
                'total' => $numeroParcelas,
                'valor' => $i === $numeroParcelas ? $valorUltima : $valorParcela,
                'data_vencimento' => $primeiroVencimento->copy()->addMonths($i - 1),
            ];
        }

        return $parcelas;
    }
}
