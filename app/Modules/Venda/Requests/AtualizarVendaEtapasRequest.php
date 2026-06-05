<?php

namespace App\Modules\Venda\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarVendaEtapasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agendamento.editar');
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'acrescimo' => ['nullable', 'numeric', 'min:0'],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
