<?php

namespace App\Modules\Pagamento\Actions;

use App\Modules\Pagamento\DTOs\RegistrarPagamentoData;
use App\Enums\StatusPagamento;
use App\Modules\Pagamento\Models\Pagamento;

class RegistrarPagamentoAction
{
    public function executar(RegistrarPagamentoData $data): Pagamento
    {
        return Pagamento::create([
            'agendamento_id' => $data->agendamento_id,
            'valor' => $data->valor,
            'forma_pagamento' => $data->forma_pagamento,
            'status' => StatusPagamento::Pago,
        ]);
    }
}
