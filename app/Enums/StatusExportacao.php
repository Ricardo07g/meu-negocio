<?php

declare(strict_types=1);

namespace App\Enums;

enum StatusExportacao: string
{
    case Processando = 'processando';
    case Pronto = 'pronto';
    case Erro = 'erro';

    public function label(): string
    {
        return match ($this) {
            self::Processando => 'Processando',
            self::Pronto => 'Pronto',
            self::Erro => 'Erro',
        };
    }

    /** Classe de cor do badge Duralux (bg-{cor}). */
    public function cor(): string
    {
        return match ($this) {
            self::Processando => 'warning',
            self::Pronto => 'success',
            self::Erro => 'danger',
        };
    }
}
