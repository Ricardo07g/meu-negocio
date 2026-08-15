<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Actions;

use App\Enums\{MotivoSemCobranca, StatusAgendamento};
use App\Exceptions\NegocioException;
use App\Modules\Agenda\Models\Agendamento;
use Illuminate\Validation\ValidationException;

class FinalizarAgendamentoAction
{
    /**
     * Encerra o atendimento com um desfecho financeiro explícito.
     *
     * Finalizar era mudo: o atendimento acontecia, virava "finalizado" e nunca
     * gerava receita — e "não cobrei de propósito" ficava indistinguível de
     * "esqueci de cobrar". Agora só há duas saídas: ou o agendamento já tem
     * título (veio de venda pré-paga ou foi cobrado agora), ou o motivo de não
     * cobrar fica registrado.
     */
    public function executar(Agendamento $agendamento, ?MotivoSemCobranca $motivoSemCobranca = null): Agendamento
    {
        if (! in_array($agendamento->status, [StatusAgendamento::Agendado, StatusAgendamento::Confirmado])) {
            throw ValidationException::withMessages([
                'status' => 'Somente agendamentos com status "agendado" ou "confirmado" podem ser finalizados.',
            ]);
        }

        $cobrado = $agendamento->foiCobrado();

        if (! $cobrado && $motivoSemCobranca === null) {
            throw new NegocioException(
                'Informe o desfecho do atendimento: cobrar o cliente ou registrar o motivo de não cobrar.'
            );
        }

        $agendamento->update([
            'status' => StatusAgendamento::Finalizado,
            // Título e motivo são mutuamente exclusivos: quem cobrou não tem por
            // que justificar, e guardar os dois criaria um registro contraditório.
            'motivo_sem_cobranca' => $cobrado ? null : $motivoSemCobranca,
        ]);

        return $agendamento->fresh();
    }
}
