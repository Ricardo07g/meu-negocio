<?php

namespace App\Modules\Produto\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CriarCategoriaProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('produto.criar');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:100'],
            'descricao' => ['nullable', 'string', 'max:255'],
        ];
    }
}
