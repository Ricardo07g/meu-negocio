<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de conta financeira (onde o dinheiro da empresa fica).
 *
 * - Caixa    : dinheiro físico (a gaveta/cofre). Tem o ritual diário de abrir/fechar/contagem.
 * - Banco    : conta bancária cadastrada pela empresa (Itaú, Nubank PJ, etc.).
 * - Carteira : carteira digital / adquirente (PicPay, Mercado Pago, etc.).
 *
 * O saldo de uma conta = saldo_inicial + créditos − débitos (lançamentos). Ver TipoLancamento.
 */
enum TipoConta: string
{
    case Caixa = 'caixa';
    case Banco = 'banco';
    case Carteira = 'carteira';

    public function label(): string
    {
        return match ($this) {
            self::Caixa => 'Caixa (dinheiro físico)',
            self::Banco => 'Conta bancária',
            self::Carteira => 'Carteira digital',
        };
    }

    /**
     * Dinheiro físico na gaveta: tem sessão diária (abrir/fechar/contagem). Só o Caixa.
     */
    public function ehCaixa(): bool
    {
        return $this === self::Caixa;
    }

    public function icone(): string
    {
        return match ($this) {
            self::Caixa => 'feather-dollar-sign',
            self::Banco => 'feather-home',
            self::Carteira => 'feather-smartphone',
        };
    }
}
