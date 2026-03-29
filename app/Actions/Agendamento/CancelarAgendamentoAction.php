<?php

namespace App\Actions\Agendamento;

use App\Enums\StatusAgendamento;
use App\Enums\StatusPagamento;
use App\Models\Agendamento;
use Illuminate\Validation\ValidationException;

class CancelarAgendamentoAction
{
    public function executar(Agendamento $agendamento): Agendamento
    {
        if ($agendamento->status === StatusAgendamento::Finalizado) {
            throw ValidationException::withMessages([
                'status' => 'Não é possível cancelar um agendamento já finalizado.',
            ]);
        }

        $agendamento->update(['status' => StatusAgendamento::Cancelado]);

        // Estornar pagamento se existir
        if ($agendamento->pagamento && $agendamento->pagamento->status === StatusPagamento::Pago) {
            $agendamento->pagamento->update(['status' => StatusPagamento::Estornado]);
        }

        return $agendamento->fresh();
    }
}
