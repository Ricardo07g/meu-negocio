<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo-base de uma forma de pagamento configurável (catálogo por rede).
 * Define o COMPORTAMENTO padrão da forma; o lojista cria formas nomeadas
 * (ex.: "Crédito Cielo") a partir de um destes tipos.
 *
 * Difere de CondicaoPagamento (eixo do cliente: à-vista x fiado) e de
 * FormaRecebimentoPrazo (canal previsto de um título a prazo).
 *
 * - Dinheiro / Pix : liquidação imediata na gaveta do caixa, sem taxa.
 * - CartaoDebito    : recebível do adquirente (≈ D+1), com taxa (MDR).
 * - CartaoCredito   : recebível do adquirente (≈ D+30), com taxa por faixa de parcelas.
 * - Boleto          : liquidação imediata no caixa quando o cliente paga o boleto.
 */
enum TipoFormaPagamento: string
{
    case Dinheiro = 'dinheiro';
    case Pix = 'pix';
    case CartaoDebito = 'cartao_debito';
    case CartaoCredito = 'cartao_credito';
    case Boleto = 'boleto';

    public function label(): string
    {
        return match ($this) {
            self::Dinheiro => 'Dinheiro',
            self::Pix => 'Pix',
            self::CartaoDebito => 'Cartão de Débito',
            self::CartaoCredito => 'Cartão de Crédito',
            self::Boleto => 'Boleto',
        };
    }

    /**
     * Se o dinheiro NÃO entra na gaveta do caixa na hora — vira um recebível do
     * banco/adquirente (D+N, líquido de taxa). Padrão do tipo; editável por forma.
     */
    public function geraRecebivelPadrao(): bool
    {
        return match ($this) {
            self::CartaoDebito, self::CartaoCredito => true,
            self::Dinheiro, self::Pix, self::Boleto => false,
        };
    }

    /**
     * Dias até a data prevista do primeiro recebível (D+N). 0 = imediato.
     */
    public function diasLiquidacaoPadrao(): int
    {
        return match ($this) {
            self::CartaoDebito => 1,
            self::CartaoCredito => 30,
            self::Dinheiro, self::Pix, self::Boleto => 0,
        };
    }

    /**
     * Se aceita parcelamento no cartão (nº de parcelas do adquirente).
     */
    public function permiteParcelasPadrao(): bool
    {
        return $this === self::CartaoCredito;
    }

    public function ehCartao(): bool
    {
        return $this === self::CartaoDebito || $this === self::CartaoCredito;
    }
}
