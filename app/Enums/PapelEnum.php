<?php

namespace App\Enums;

enum PapelEnum: string
{
    case Admin = 'Admin';
    case Gerente = 'Gerente';
    case Profissional = 'Profissional';
    case Recepcao = 'Recepcao';
    case Financeiro = 'Financeiro';
    case Estoque = 'Estoque';
    case Visualizador = 'Visualizador';
}
