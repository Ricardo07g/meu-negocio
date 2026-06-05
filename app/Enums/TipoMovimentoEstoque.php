<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoMovimentoEstoque: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';
    case Ajuste = 'ajuste';
}
