<?php

namespace App\Modules\Produto\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('produto.editar');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:200'],
            'quantidade' => ['required', 'integer', 'min:0'],
            'valor' => ['required', 'numeric', 'min:0'],
        ];
    }
}
