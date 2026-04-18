<?php

namespace App\Modules\Caixa\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MovimentoCaixaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('financeiro.criar');
    }

    public function rules(): array
    {
        return [
            'valor' => ['required', 'numeric', 'min:0.01'],
            'descricao' => ['required', 'string', 'max:255'],
        ];
    }
}
