<?php

namespace App\Enums;

enum TipoServico: string
{
    case Unico = 'unico';
    case Etapas = 'etapas';

    public function label(): string
    {
        return match ($this) {
            self::Unico => 'Serviço Único',
            self::Etapas => 'Serviço em Etapas',
        };
    }
}
