<?php

namespace App\DTO\Pagamento;

use App\Enums\FormaPagamento;
use Spatie\LaravelData\Data;

class RegistrarPagamentoData extends Data
{
    public function __construct(
        public float $valor,
        public FormaPagamento $forma_pagamento,
        public ?int $agendamento_id = null,
    ) {}
}
