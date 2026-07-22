<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status de um recebível de cartão (a receber do banco/adquirente).
 * Derivado pela data (sem job): antes da data prevista é Previsto, na data ou
 * depois é Recebido; cancelado quando a venda de origem é estornada.
 *
 * - Previsto  : ainda vai cair (data_prevista no futuro).
 * - Recebido  : data prevista alcançada (assume-se que o banco pagou).
 * - Cancelado : venda estornada — o recebível não vai ocorrer.
 */
enum StatusRecebivel: string
{
    case Previsto = 'previsto';
    case Recebido = 'recebido';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Previsto => 'A receber',
            self::Recebido => 'Recebido',
            self::Cancelado => 'Cancelado',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Previsto => 'warning',
            self::Recebido => 'success',
            self::Cancelado => 'secondary',
        };
    }
}
