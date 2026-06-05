<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Actions;

use App\Enums\{StatusAgendamento, StatusPagamento};
use App\Modules\Agenda\Models\Agendamento;
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
