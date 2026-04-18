<?php

namespace App\Modules\Produto\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalvarCategoriaProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? $this->user()->can('produto.criar')
            : $this->user()->can('produto.editar');
    }

    public function rules(): array
    {
        return [
            'descricao' => ['required', 'string', 'max:255'],
            'ativo' => ['nullable', 'boolean'],
        ];
    }
}
