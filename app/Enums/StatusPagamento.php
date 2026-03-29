<?php

namespace App\Enums;

enum StatusPagamento: string
{
    case Pendente = 'pendente';
    case Pago = 'pago';
    case Cancelado = 'cancelado';
    case Estornado = 'estornado';
}
