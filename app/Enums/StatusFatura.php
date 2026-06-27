<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status da fatura mensal da assinatura (cobranca interna, sem gateway).
 * Os valores string sao identicos aos historicos para nao exigir migration
 * de dados — o enum vive apenas na camada PHP (cast no Model Fatura).
 */
enum StatusFatura: string
{
    case EmAberto = 'em_aberto';   // gerada, ainda nao paga
    case Paga = 'paga';            // quitada (pago_em preenchido)
    case Vencida = 'vencida';      // passou do vencimento sem pagamento
    case Cancelada = 'cancelada';  // anulada

    public function label(): string
    {
        return match ($this) {
            self::EmAberto => 'Em aberto',
            self::Paga => 'Paga',
            self::Vencida => 'Vencida',
            self::Cancelada => 'Cancelada',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::EmAberto => 'warning',
            self::Paga => 'success',
            self::Vencida => 'danger',
            self::Cancelada => 'secondary',
        };
    }
}
