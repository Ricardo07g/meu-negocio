<?php

namespace App\Enums;

enum FormaPagamento: string
{
    case Pix = 'pix';
    case Dinheiro = 'dinheiro';
    case Cartao = 'cartao';
}
