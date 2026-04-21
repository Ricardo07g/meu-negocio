<?php

namespace App\Enums;

enum StatusAgendamento: string
{
    case Agendado = 'agendado';
    case Confirmado = 'confirmado';
    case Cancelado = 'cancelado';
    case Finalizado = 'finalizado';

    public function label(): string
    {
        return match ($this) {
            self::Agendado => 'Agendado',
            self::Confirmado => 'Confirmado',
            self::Cancelado => 'Cancelado',
            self::Finalizado => 'Finalizado',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Agendado => 'info',
            self::Confirmado => 'primary',
            self::Cancelado => 'danger',
            self::Finalizado => 'success',
        };
    }
}
