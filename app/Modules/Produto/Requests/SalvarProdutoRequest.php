<?php

namespace App\Modules\Produto\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalvarProdutoRequest extends FormRequest
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
            'nome' => ['required', 'string', 'max:200'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'codigo_barras' => ['nullable', 'string', 'max:50'],
            'descricao' => ['nullable', 'string'],
            'categoria_produto_id' => ['nullable', 'exists:categorias_produto,id'],
            'quantidade' => ['required', 'integer', 'min:0'],
            'valor_custo' => ['nullable', 'numeric', 'min:0'],
            'valor_venda' => ['required', 'numeric', 'min:0'],
            'estoque_minimo' => ['nullable', 'integer', 'min:0'],
            'unidade' => ['nullable', 'string', 'max:20'],
            'ativo' => ['nullable', 'boolean'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}
