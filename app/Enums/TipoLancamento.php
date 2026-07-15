<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de lançamento no razão de uma conta (App\Modules\Conta).
 *
 * - Credito : entrou dinheiro na conta (venda à vista, recebível liquidado, aporte, transferência).
 * - Debito  : saiu dinheiro da conta (despesa paga, tarifa, sangria, transferência, estorno).
 *
 * Saldo da conta = saldo_inicial + Σ(crédito) − Σ(débito).
 */
enum TipoLancamento: string
{
    case Credito = 'credito';
    case Debito = 'debito';

    public function label(): string
    {
        return match ($this) {
            self::Credito => 'Crédito',
            self::Debito => 'Débito',
        };
    }

    /** Sinal do lançamento no saldo: +1 para crédito, −1 para débito. */
    public function sinal(): int
    {
        return match ($this) {
            self::Credito => 1,
            self::Debito => -1,
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Credito => 'success',
            self::Debito => 'danger',
        };
    }
}
