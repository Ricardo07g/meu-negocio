<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DTOs;

use App\Modules\Tenant\Models\{Fatura, Plano};
use Carbon\Carbon;

/**
 * Resultado de uma troca de plano (ADR-0008). Transporta o tipo de transicao
 * (upgrade imediato, upgrade sem ajuste, downgrade agendado ou cancelamento de
 * agendamento) e os dados necessarios para a mensagem ao usuario, evitando que
 * o controller precise re-deduzir a intencao.
 */
final class ResultadoTransicao
{
    public const UPGRADE_AJUSTADO = 'upgrade_ajustado';

    public const UPGRADE_SEM_AJUSTE = 'upgrade_sem_ajuste';

    public const DOWNGRADE_AGENDADO = 'downgrade_agendado';

    public const AGENDAMENTO_CANCELADO = 'agendamento_cancelado';

    private function __construct(
        public readonly string $tipo,
        public readonly Plano $destino,
        public readonly float $valorVigente = 0.0,
        public readonly ?Carbon $vigenciaEm = null,
    ) {}

    public static function upgradeAjustado(Plano $destino, Fatura $fatura): self
    {
        return new self(self::UPGRADE_AJUSTADO, $destino, valorVigente: (float) $fatura->valor);
    }

    public static function upgradeSemAjuste(Plano $destino): self
    {
        return new self(self::UPGRADE_SEM_AJUSTE, $destino);
    }

    public static function downgradeAgendado(Plano $destino, Carbon $vigenciaEm): self
    {
        return new self(self::DOWNGRADE_AGENDADO, $destino, vigenciaEm: $vigenciaEm);
    }

    public static function agendamentoCancelado(Plano $destino): self
    {
        return new self(self::AGENDAMENTO_CANCELADO, $destino);
    }

    public function mensagem(): string
    {
        $nome = ucfirst($this->destino->nome);

        return match ($this->tipo) {
            self::UPGRADE_AJUSTADO => "Plano alterado para \"{$nome}\". Fatura do mes ajustada para R$ "
                .number_format($this->valorVigente, 2, ',', '.').'.',
            self::UPGRADE_SEM_AJUSTE => "Plano alterado para \"{$nome}\". A fatura deste mes nao muda; "
                .'o novo valor passa a valer na proxima fatura.',
            self::DOWNGRADE_AGENDADO => "Mudanca para o plano \"{$nome}\" agendada para "
                .($this->vigenciaEm?->format('d/m/Y') ?? '-').'. Voce mantem o plano atual ate la.',
            self::AGENDAMENTO_CANCELADO => 'Mudanca de plano agendada cancelada. Seu plano atual foi mantido.',
            default => 'Plano atualizado.',
        };
    }
}
