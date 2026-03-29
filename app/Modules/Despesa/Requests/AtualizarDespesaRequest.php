<?php

namespace App\Modules\Despesa\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarDespesaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('despesa.editar');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:200'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'data' => ['required', 'date'],
        ];
    }
}
