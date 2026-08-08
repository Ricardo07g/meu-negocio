<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Natureza de uma linha da timeline "Movimentações do dia" (tela do Caixa).
 *
 * Diferente de TipoLancamento (que é o eixo contábil de UMA conta), aqui o eixo é
 * o FLUXO do dia da loja: o que movimentou dinheiro, venha de onde vier.
 *
 * - Venda       : recebimento de uma venda à vista (dinheiro/cartão/pix, na hora). - Recebimento : baixa de parcela de título a prazo (a venda foi em outro dia). - Despesa     : conta a pagar quitada (baixa de despesa).
 * - Estorno     : venda cancelada — o recebimento do dia foi desfeito.
 * - Sangria     : retirada de dinheiro da gaveta (evento nativo do caixa, sem baixa).
 * - Reforco     : aporte de dinheiro na gaveta (idem).
 */
enum TipoMovimentacaoDia: string
{
    case Venda = 'venda';
    case Recebimento = 'recebimento';
    case Despesa = 'despesa';
    case Estorno = 'estorno';
    case Sangria = 'sangria';
    case Reforco = 'reforco';

    public function label(): string
    {
        return match ($this) {
            self::Venda => 'Venda',
            self::Recebimento => 'Recebimento',
            self::Despesa => 'Despesa',
            self::Estorno => 'Estorno',
            self::Sangria => 'Sangria',
            self::Reforco => 'Reforço',
        };
    }

    /** Dinheiro entrando no negócio (define o sinal e a cor do valor). */
    public function ehEntrada(): bool
    {
        return match ($this) {
            self::Venda, self::Recebimento, self::Reforco => true,
            self::Despesa, self::Estorno, self::Sangria => false,
        };
    }

    /** Classe do badge no padrão Duralux (bg-soft-* + text-*). */
    public function cor(): string
    {
        return match ($this) {
            self::Venda => 'success',
            self::Recebimento => 'primary',
            self::Despesa => 'danger',
            self::Estorno => 'secondary',
            self::Sangria => 'warning',
            self::Reforco => 'info',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Venda => 'feather-shopping-bag',
            self::Recebimento => 'feather-download',
            self::Despesa => 'feather-file-text',
            self::Estorno => 'feather-rotate-ccw',
            self::Sangria => 'feather-minus-circle',
            self::Reforco => 'feather-plus-circle',
        };
    }

    /**
     * Entra no "Entradas do dia" / "Saídas do dia"? Sangria e reforço NÃO: são
     * transferência de dinheiro entre a gaveta e o bolso/banco, não receita nem
     * despesa do negócio — contá-los inflaria o resultado do dia.
     */
    public function contaNoResultado(): bool
    {
        return match ($this) {
            self::Venda, self::Recebimento, self::Despesa, self::Estorno => true,
            self::Sangria, self::Reforco => false,
        };
    }
}
