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
 * - Dinheiro        : liquidação imediata na gaveta do caixa, sem taxa.
 * - Pix             : cai numa conta (banco/carteira), nunca na gaveta. Recebível CONFIGURÁVEL —
 *                     direto ao banco (D+0, sem taxa) ou via maquineta/adquirente (D+N, com taxa).
 * - CartaoDebito    : recebível do adquirente (≈ D+1), com taxa (MDR).
 * - CartaoCredito   : recebível do adquirente (≈ D+30), com taxa por faixa de parcelas.
 * - Boleto          : liquidação imediata no caixa quando o cliente paga o boleto.
 * - Crediario       : a loja financia o cliente (a receber do cliente, N parcelas). Força a-prazo;
 *                     NÃO gera recebível de banco.
 */
enum TipoFormaPagamento: string
{
    case Dinheiro = 'dinheiro';
    case Pix = 'pix';
    case CartaoDebito = 'cartao_debito';
    case CartaoCredito = 'cartao_credito';
    case Boleto = 'boleto';
    case Crediario = 'crediario';

    public function label(): string
    {
        return match ($this) {
            self::Dinheiro => 'Dinheiro',
            self::Pix => 'Pix',
            self::CartaoDebito => 'Cartão de Débito',
            self::CartaoCredito => 'Cartão de Crédito',
            self::Boleto => 'Boleto',
            self::Crediario => 'Crediário',
        };
    }

    /**
     * Se o dinheiro NÃO entra na gaveta do caixa na hora — vira um recebível do
     * banco/adquirente (D+N, líquido de taxa). Para o PIX é apenas o PADRÃO
     * (recebível configurável, ver recebivelConfiguravel); para os demais é
     * derivado do tipo (não editável).
     */
    public function geraRecebivelPadrao(): bool
    {
        return match ($this) {
            self::CartaoDebito, self::CartaoCredito => true,
            self::Dinheiro, self::Pix, self::Boleto, self::Crediario => false,
        };
    }

    /**
     * Se o lojista pode escolher se a forma gera recebível (diferido, D+N) ou cai
     * imediato na conta. Só o PIX: pode ser direto ao banco (imediato) ou via
     * maquineta/adquirente (recebível). Nos demais tipos o comportamento é fixo.
     */
    public function recebivelConfiguravel(): bool
    {
        return $this === self::Pix;
    }

    /**
     * Se a natureza da forma é cair na conta CAIXA (gaveta física) quando não gera
     * recebível: dinheiro, boleto e crediário. O PIX cai em banco/carteira. Usado
     * como fallback ao resolver a conta destino quando a forma não a define.
     */
    public function destinoNaturalCaixa(): bool
    {
        return match ($this) {
            self::Dinheiro, self::Boleto, self::Crediario => true,
            self::Pix, self::CartaoDebito, self::CartaoCredito => false,
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
            self::Dinheiro, self::Pix, self::Boleto, self::Crediario => 0,
        };
    }

    /**
     * Se aceita parcelamento no cartão (nº de parcelas do adquirente + faixas de taxa).
     * DERIVADO do tipo: só o crédito. (Crediário parcela o CLIENTE, não o cartão — ver forcaAPrazo.)
     */
    public function permiteParcelasPadrao(): bool
    {
        return $this === self::CartaoCredito;
    }

    public function ehCartao(): bool
    {
        return $this === self::CartaoDebito || $this === self::CartaoCredito;
    }

    public function ehCrediario(): bool
    {
        return $this === self::Crediario;
    }

    /**
     * Se, ao ser escolhida na venda, a forma FORÇA condição "a prazo" (a loja financia o
     * cliente em N parcelas). Espelho invertido do cartão, que força "à vista".
     */
    public function forcaAPrazo(): bool
    {
        return $this === self::Crediario;
    }

    /** Usa taxa plana (MDR único): débito e PIX-maquineta. O crédito usa faixas por parcela. */
    public function usaTaxaPlana(): bool
    {
        return $this === self::CartaoDebito || $this === self::Pix;
    }

    /** Usa faixas de taxa por nº de parcelas do cartão: só o crédito. */
    public function usaFaixas(): bool
    {
        return $this === self::CartaoCredito;
    }

    /** Aceita configuração de antecipação (adiantamento do adquirente): cartões. */
    public function usaAntecipacao(): bool
    {
        return $this->ehCartao();
    }

    /** Tem prazo de liquidação (D+N) configurável: cartões e PIX (quando via maquineta). */
    public function usaLiquidacao(): bool
    {
        return $this->ehCartao() || $this === self::Pix;
    }

    /**
     * Se a forma EXIGE uma conta destino explícita. Cartão (débito/crédito) e PIX
     * nunca caem na gaveta: cada maquineta/canal liquida numa conta própria
     * (banco/carteira), então o lojista deve escolher qual. Dinheiro/Boleto/Crediário
     * caem no Caixa por natureza — conta destino opcional.
     */
    public function exigeContaDestino(): bool
    {
        return match ($this) {
            self::CartaoDebito, self::CartaoCredito, self::Pix => true,
            self::Dinheiro, self::Boleto, self::Crediario => false,
        };
    }
}
