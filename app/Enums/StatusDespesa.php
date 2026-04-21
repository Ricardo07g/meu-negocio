<?php

namespace App\Enums;

enum StatusDespesa: string
{
    case Pendente = 'pendente';
    case Paga = 'paga';
    case Cancelada = 'cancelada';
}
