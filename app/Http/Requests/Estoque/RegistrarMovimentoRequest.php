<?php

namespace App\Http\Requests\Estoque;

use App\Enums\TipoMovimentoEstoque;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarMovimentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('movimento_estoque.criar');
    }

    public function rules(): array
    {
        return [
            'produto_id' => ['required', 'exists:produtos,id'],
            'tipo' => ['required', Rule::enum(TipoMovimentoEstoque::class)],
            'quantidade' => ['required', 'integer', 'min:1'],
        ];
    }
}
