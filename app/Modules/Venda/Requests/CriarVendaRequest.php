<?php

namespace App\Modules\Venda\Requests;

use App\Modules\Servico\Models\Servico;
use Illuminate\Foundation\Http\FormRequest;

class CriarVendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agendamento.criar');
    }

    public function rules(): array
    {
        $tipoVenda = $this->input('tipo_venda', 'servico');

        $pagamentoRules = [
            'forma_pagamento' => ['required', 'in:pix,dinheiro,cartao,fiado'],
            'status_pagamento' => ['required', 'in:pago,pendente'],
        ];

        if ($tipoVenda === 'produto') {
            return array_merge([
                'tipo_venda' => ['required', 'in:servico,produto'],
                'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
                'produto_id' => ['required', 'integer', 'exists:produtos,id'],
                'quantidade' => ['required', 'integer', 'min:1'],
                'valor_total' => ['required', 'numeric', 'min:0.01'],
            ], $pagamentoRules);
        }

        $servico = Servico::find($this->input('servico_id'));
        $isPacote = $servico && $servico->isPacote();

        $rules = [
            'tipo_venda' => ['required', 'in:servico,produto'],
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'servico_id' => ['required', 'integer', 'exists:servicos,id'],
            'atendente_id' => ['required', 'integer', 'exists:usuarios,id'],
        ];

        if ($isPacote) {
            $rules += [
                'valor_total' => ['required', 'numeric', 'min:0.01'],
                'horario' => ['required', 'date_format:H:i'],
                'datas' => ['required', 'array', 'min:1'],
                'datas.*' => ['required', 'date_format:Y-m-d'],
                'horarios' => ['nullable', 'array'],
                'horarios.*' => ['nullable', 'date_format:H:i'],
            ];
        } else {
            $rules += [
                'inicio' => ['required', 'date'],
                'fim' => ['nullable', 'date', 'after:inicio'],
            ];
        }

        return array_merge($rules, $pagamentoRules);
    }
}
