<?php

namespace App\Enums;

enum StatusVendaEtapas: string
{
    case Ativo = 'ativo';
    case Concluido = 'concluido';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Ativo => 'Ativo',
            self::Concluido => 'Concluído',
            self::Cancelado => 'Cancelado',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Ativo => 'success',
            self::Concluido => 'primary',
            self::Cancelado => 'danger',
        };
    }
}
