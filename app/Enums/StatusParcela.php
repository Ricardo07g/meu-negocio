<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status individual de uma parcela (de contas a receber ou a pagar).
 *
 * - Pendente     : aguardando recebimento/pagamento.
 * - Pago         : quitada totalmente.
 * - Vencido      : derivado (status visual quando pendente e vencimento no passado);
 *                  também gravado ao renegociar/baixar para indicar que estava atrasada.
 * - Cancelado    : parcela abandonada (ex: estorno, cliente desistiu).
 * - Renegociado  : teve vencimento ou valor alterado via processo de renegociação.
 */
enum StatusParcela: string
{
    case Pendente = 'pendente';
    case Pago = 'pago';
    case Vencido = 'vencido';
    case Cancelado = 'cancelado';
    case Renegociado = 'renegociado';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Pago => 'Pago',
            self::Vencido => 'Vencido',
            self::Cancelado => 'Cancelado',
            self::Renegociado => 'Renegociado',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Pendente => 'warning',
            self::Pago => 'success',
            self::Vencido => 'danger',
            self::Cancelado => 'secondary',
            self::Renegociado => 'info',
        };
    }
}
