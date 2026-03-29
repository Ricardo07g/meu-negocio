<?php

namespace App\Enums;

enum StatusAgendamento: string
{
    case Agendado = 'agendado';
    case Confirmado = 'confirmado';
    case Cancelado = 'cancelado';
    case Finalizado = 'finalizado';
}
