<?php

namespace App\Modules\Despesa\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalvarCategoriaDespesaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? $this->user()->can('categoria_despesa.criar')
            : $this->user()->can('categoria_despesa.editar');
    }

    public function rules(): array
    {
        return [
            'descricao' => ['required', 'string', 'max:100'],
            'ativo' => ['nullable', 'boolean'],
        ];
    }
}
