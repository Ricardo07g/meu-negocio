<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Actions;

use App\Enums\StatusAgendamento;
use App\Modules\Agenda\Models\Agendamento;
use Illuminate\Validation\ValidationException;

class FinalizarAgendamentoAction
{
    public function executar(Agendamento $agendamento): Agendamento
    {
        if (! in_array($agendamento->status, [StatusAgendamento::Agendado, StatusAgendamento::Confirmado])) {
            throw ValidationException::withMessages([
                'status' => 'Somente agendamentos com status "agendado" ou "confirmado" podem ser finalizados.',
            ]);
        }

        $agendamento->update(['status' => StatusAgendamento::Finalizado]);

        return $agendamento->fresh();
    }
}
