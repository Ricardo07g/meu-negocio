<?php

declare(strict_types=1);

namespace App\Modules\Venda\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarVendaProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agendamento.editar');
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.id' => ['nullable', 'integer', 'exists:venda_produto_itens,id'],
            'itens.*.produto_id' => ['required', 'integer', 'exists:produtos,id'],
            'itens.*.quantidade' => ['required', 'integer', 'min:1'],
            'itens.*.valor_unitario' => ['required', 'numeric', 'min:0'],
            'itens.*.desconto' => ['nullable', 'numeric', 'min:0'],
            'itens.*.acrescimo' => ['nullable', 'numeric', 'min:0'],
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'acrescimo' => ['nullable', 'numeric', 'min:0'],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
