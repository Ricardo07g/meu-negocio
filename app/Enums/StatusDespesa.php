<?php

namespace App\Enums;

/**
 * Status agregado do título Despesa (conta a pagar).
 * Derivado do status das parcelas, persistido para consulta rápida.
 */
enum StatusDespesa: string
{
    case Pendente = 'pendente';
    case Parcial = 'parcial';
    case Paga = 'paga';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Parcial => 'Parcial',
            self::Paga => 'Paga',
            self::Cancelada => 'Cancelada',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Pendente => 'warning',
            self::Parcial => 'info',
            self::Paga => 'success',
            self::Cancelada => 'secondary',
        };
    }
}
