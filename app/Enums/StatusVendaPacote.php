<?php

namespace App\Enums;

enum StatusVendaPacote: string
{
    case Ativo = 'ativo';
    case Concluido = 'concluido';
    case Cancelado = 'cancelado';
}
