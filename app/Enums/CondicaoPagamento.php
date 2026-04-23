<?php

namespace App\Enums;

/**
 * Condição/modalidade do título (Pagamento ou Despesa).
 * Define se há parcelamento e como são geradas as parcelas.
 *
 * - AVista        : 1 parcela única, vencimento no ato.
 * - APrazo        : N parcelas com vencimento mensal (forma escolhida na criação).
 * - Boleto        : N parcelas emitidas via boleto (futuro: integração bancária).
 * - PixParcelado  : N parcelas via Pix recorrente (futuro).
 */
enum CondicaoPagamento: string
{
    case AVista = 'a_vista';
    case APrazo = 'a_prazo';
    case Boleto = 'boleto';
    case PixParcelado = 'pix_parcelado';

    public function label(): string
    {
        return match ($this) {
            self::AVista => 'À Vista',
            self::APrazo => 'A Prazo',
            self::Boleto => 'Boleto',
            self::PixParcelado => 'Pix Parcelado',
        };
    }

    public function geraParcelas(): bool
    {
        return match ($this) {
            self::AVista => false,
            self::APrazo, self::Boleto, self::PixParcelado => true,
        };
    }

    public function exigeFormaNaCriacao(): bool
    {
        // À vista e a prazo exigem a forma no ato — a_prazo registra a forma prevista em cada parcela
        return $this === self::AVista || $this === self::APrazo;
    }
}
