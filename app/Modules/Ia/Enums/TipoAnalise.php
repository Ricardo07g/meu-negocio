<?php

declare(strict_types=1);

namespace App\Modules\Ia\Enums;

enum TipoAnalise: string
{
    /** Segmentacao RFM da carteira inteira da unidade. */
    case CarteiraRfm = 'carteira_rfm';

    /** Comportamento de compra de um cliente especifico. */
    case ClienteComportamento = 'cliente_comportamento';

    public function label(): string
    {
        return match ($this) {
            self::CarteiraRfm => 'Carteira de clientes',
            self::ClienteComportamento => 'Comportamento do cliente',
        };
    }
}
