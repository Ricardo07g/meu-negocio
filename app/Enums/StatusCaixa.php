<?php

declare(strict_types=1);

namespace App\Enums;

enum StatusCaixa: string
{
    case Aberto = 'aberto';
    case Fechado = 'fechado';
}
