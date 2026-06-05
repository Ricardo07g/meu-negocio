<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoMovimentoCaixa: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';
    case Sangria = 'sangria';
    case Reforco = 'reforco';
}
