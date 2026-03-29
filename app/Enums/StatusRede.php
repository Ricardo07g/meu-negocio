<?php

namespace App\Enums;

enum StatusRede: string
{
    case Ativa = 'ativa';
    case Inativa = 'inativa';
    case Suspensa = 'suspensa';
    case Cancelada = 'cancelada';
}
