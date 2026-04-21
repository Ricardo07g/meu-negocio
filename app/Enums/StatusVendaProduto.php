<?php

namespace App\Enums;

enum StatusVendaProduto: string
{
    case Ativa = 'ativa';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Ativa => 'Ativa',
            self::Cancelada => 'Cancelada',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Ativa => 'success',
            self::Cancelada => 'danger',
        };
    }
}
