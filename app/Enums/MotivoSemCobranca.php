<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Por que um atendimento foi finalizado sem virar receita.
 *
 * Existe para que "nao cobrei de proposito" seja um fato registrado, e nao o
 * mesmo silencio de "esqueci de cobrar". Todo agendamento finalizado sem titulo
 * carrega um destes.
 */
enum MotivoSemCobranca: string
{
    case Cortesia = 'cortesia';
    case Retorno = 'retorno';
    case Garantia = 'garantia';
    case Interno = 'interno';

    public function label(): string
    {
        return match ($this) {
            self::Cortesia => 'Cortesia',
            self::Retorno => 'Retorno / revisão',
            self::Garantia => 'Garantia',
            self::Interno => 'Uso interno',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Cortesia => 'Atendimento oferecido sem cobrança.',
            self::Retorno => 'Retorno de um atendimento já pago.',
            self::Garantia => 'Refação coberta por garantia.',
            self::Interno => 'Atendimento da própria equipe.',
        };
    }
}
