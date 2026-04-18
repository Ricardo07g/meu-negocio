<?php

namespace App\Modules\Caixa\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AbrirCaixaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('financeiro.criar');
    }

    public function rules(): array
    {
        return [
            'saldo_abertura' => ['required', 'numeric', 'min:0'],
            'data' => ['required', 'date', 'before_or_equal:today'],
            'observacao' => ['nullable', 'string'],
        ];
    }
}
