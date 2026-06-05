<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Forma prevista de recebimento quando a condição é "A Prazo".
 * Descreve COMO as parcelas serão cobradas / apresentadas ao cliente.
 *
 * Novas formas (boleto registrado, pix parcelado, etc) devem ser
 * adicionadas aqui no futuro — sem afetar o enum de CondicaoPagamento
 * (que continua com apenas AVista/APrazo).
 */
enum FormaRecebimentoPrazo: string
{
    case Carne = 'carne';

    public function label(): string
    {
        return match ($this) {
            self::Carne => 'Carnê',
        };
    }
}
