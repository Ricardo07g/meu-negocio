<?php

namespace App\Actions\Pagamento;

use App\DTO\Pagamento\RegistrarPagamentoData;
use App\Enums\StatusPagamento;
use App\Models\Pagamento;

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
