<?php

namespace App\Enums;

enum TipoMovimentoEstoque: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';
    case Ajuste = 'ajuste';
}
