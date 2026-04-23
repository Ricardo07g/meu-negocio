<?php

namespace App\Enums;

/**
 * Forma real usada numa baixa (recebimento ou pagamento efetivo).
 * Diferente de CondicaoPagamento — esta descreve COMO o dinheiro entrou/saiu.
 */
enum FormaPagamento: string
{
    case Pix = 'pix';
    case Dinheiro = 'dinheiro';
    case Cartao = 'cartao';
    case Boleto = 'boleto';

    public function label(): string
    {
        return match ($this) {
            self::Pix => 'Pix',
            self::Dinheiro => 'Dinheiro',
            self::Cartao => 'Cartão',
            self::Boleto => 'Boleto',
        };
    }
}
