<?php

namespace App\Actions\Agendamento;

use App\Enums\StatusAgendamento;
use App\Models\Agendamento;
use Illuminate\Validation\ValidationException;

class FinalizarAgendamentoAction
{
    public function executar(Agendamento $agendamento): Agendamento
    {
        if (!in_array($agendamento->status, [StatusAgendamento::Agendado, StatusAgendamento::Confirmado])) {
            throw ValidationException::withMessages([
                'status' => 'Somente agendamentos com status "agendado" ou "confirmado" podem ser finalizados.',
            ]);
        }

        $agendamento->update(['status' => StatusAgendamento::Finalizado]);

        return $agendamento->fresh();
    }
}
