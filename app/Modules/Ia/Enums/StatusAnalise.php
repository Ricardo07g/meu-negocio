<?php

declare(strict_types=1);

namespace App\Modules\Ia\Enums;

enum StatusAnalise: string
{
    case Ok = 'ok';
    case Erro = 'erro';

    /** Recusada por cota diaria estourada. Grava linha de proposito: entra na medicao. */
    case RecusadoCota = 'recusado_cota';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Concluida',
            self::Erro => 'Erro',
            self::RecusadoCota => 'Cota diaria atingida',
        };
    }

    /** Classe de cor do badge Duralux (bg-{cor}). */
    public function cor(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::Erro => 'danger',
            self::RecusadoCota => 'warning',
        };
    }
}
