<?php

namespace App\Modules\Produto\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarCategoriaProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('produto.editar');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:100'],
            'descricao' => ['nullable', 'string', 'max:255'],
        ];
    }
}
