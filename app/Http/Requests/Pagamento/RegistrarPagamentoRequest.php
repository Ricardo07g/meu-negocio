<?php

namespace App\Http\Requests\Pagamento;

use App\Enums\FormaPagamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarPagamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pagamento.criar');
    }

    public function rules(): array
    {
        return [
            'valor' => ['required', 'numeric', 'min:0.01'],
            'forma_pagamento' => ['required', Rule::enum(FormaPagamento::class)],
            'agendamento_id' => ['nullable', 'exists:agendamentos,id'],
        ];
    }
}
