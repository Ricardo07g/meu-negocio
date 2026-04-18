<?php

namespace App\Enums;

enum TipoMovimentoCaixa: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';
    case Sangria = 'sangria';
    case Reforco = 'reforco';
}
