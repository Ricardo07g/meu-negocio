<?php

namespace App\Modules\Pagamento\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalvarBaixaParcelaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'valor' => ['required', 'numeric', 'min:0.01'],
            'multa' => ['nullable', 'numeric', 'min:0'],
            'juros' => ['nullable', 'numeric', 'min:0'],
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'forma_pagamento' => ['required', 'string'],
            'observacao' => ['nullable', 'string'],
        ];
    }
}
