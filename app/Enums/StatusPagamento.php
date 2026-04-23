<?php

namespace App\Enums;

/**
 * Status agregado do título Pagamento (conta a receber).
 * Derivado do status das parcelas, persistido para consulta rápida.
 */
enum StatusPagamento: string
{
    case Pendente = 'pendente';   // nenhuma parcela paga
    case Parcial = 'parcial';     // pelo menos uma parcela paga, mas resta saldo
    case Pago = 'pago';           // todas parcelas pagas
    case Cancelado = 'cancelado'; // todas parcelas canceladas
    case Estornado = 'estornado'; // venda cancelada — parcelas foram canceladas/estornadas

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Parcial => 'Parcial',
            self::Pago => 'Pago',
            self::Cancelado => 'Cancelado',
            self::Estornado => 'Estornado',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Pendente => 'warning',
            self::Parcial => 'info',
            self::Pago => 'success',
            self::Cancelado => 'danger',
            self::Estornado => 'secondary',
        };
    }
}
