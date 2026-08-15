<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * O que a recepcao precisa saber antes de liberar o cliente: este atendimento
 * ja virou dinheiro?
 *
 * Derivado, nunca persistido — a verdade e o titulo em `pagamentos.agendamento_id`
 * (e a ausencia dele). Agendamento e o fato operacional; venda e o financeiro.
 * Guardar isto numa coluna seria criar uma segunda verdade que envelhece a cada
 * baixa ou estorno.
 */
enum SituacaoFinanceiraAgendamento: string
{
    case ACobrar = 'a_cobrar';         // sem titulo, atendimento ainda em aberto
    case SemCobranca = 'sem_cobranca'; // finalizado de proposito sem cobrar
    case AReceber = 'a_receber';       // titulo pendente ou parcial
    case Pago = 'pago';
    case Estornado = 'estornado';

    public function label(): string
    {
        return match ($this) {
            self::ACobrar => 'A cobrar',
            self::SemCobranca => 'Sem cobrança',
            self::AReceber => 'A receber',
            self::Pago => 'Pago',
            self::Estornado => 'Estornado',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::ACobrar => 'warning',
            self::SemCobranca => 'secondary',
            self::AReceber => 'info',
            self::Pago => 'success',
            self::Estornado => 'danger',
        };
    }
}
