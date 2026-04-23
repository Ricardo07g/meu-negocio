<?php

namespace App\Modules\Venda\Requests;

use App\Enums\CondicaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\FormaRecebimentoPrazo;
use App\Modules\Servico\Models\Servico;
use App\Support\Parcelamento\CalculadoraParcelas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CriarVendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agendamento.criar');
    }

    public function rules(): array
    {
        $tipoVenda = $this->input('tipo_venda', 'servico');

        $condicoesParceladas = [
            CondicaoPagamento::APrazo->value,
        ];

        $condicoesHabilitadas = [
            CondicaoPagamento::AVista->value,
            CondicaoPagamento::APrazo->value,
        ];

        $condicoesComForma = [
            CondicaoPagamento::AVista->value,
            CondicaoPagamento::APrazo->value,
        ];

        $pagamentoRules = [
            'condicao_pagamento' => ['required', Rule::in($condicoesHabilitadas)],
            'mes_referencia' => ['required', 'date'],
            'forma_pagamento' => [
                'required_if:condicao_pagamento,' . implode(',', $condicoesComForma),
                'nullable',
                Rule::enum(FormaPagamento::class),
            ],
            'numero_parcelas' => [
                'required_if:condicao_pagamento,' . implode(',', $condicoesParceladas),
                'nullable',
                'integer',
                'min:2',
                'max:' . CalculadoraParcelas::MAX_PARCELAS,
            ],
            'primeiro_vencimento' => [
                'required_if:condicao_pagamento,' . implode(',', $condicoesParceladas),
                'nullable',
                'date',
                'after_or_equal:today',
            ],
            'forma_recebimento_prazo' => [
                'required_if:condicao_pagamento,' . implode(',', $condicoesParceladas),
                'nullable',
                Rule::enum(FormaRecebimentoPrazo::class),
            ],
            'parcelas' => ['nullable', 'array'],
            'parcelas.*.numero' => ['required_with:parcelas', 'integer', 'min:1'],
            'parcelas.*.total' => ['required_with:parcelas', 'integer', 'min:1'],
            'parcelas.*.valor' => ['required_with:parcelas', 'numeric', 'min:0.01'],
            'parcelas.*.data_vencimento' => ['required_with:parcelas', 'date'],
            'parcelas.*.mes_referencia' => ['required_with:parcelas', 'date'],
        ];

        if ($tipoVenda === 'produto') {
            return array_merge([
                'tipo_venda' => ['required', 'in:servico,produto'],
                'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
                'itens' => ['required', 'array', 'min:1'],
                'itens.*.produto_id' => ['required', 'integer', 'exists:produtos,id'],
                'itens.*.quantidade' => ['required', 'integer', 'min:1'],
                'itens.*.valor_unitario' => ['required', 'numeric', 'min:0'],
                'itens.*.desconto' => ['nullable', 'numeric', 'min:0'],
                'itens.*.acrescimo' => ['nullable', 'numeric', 'min:0'],
                'data' => ['nullable', 'date'],
                'observacao' => ['nullable', 'string'],
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
                'data' => ['required', 'date_format:Y-m-d'],
                'horario' => ['required', 'date_format:H:i'],
            ];
        }

        return array_merge($rules, $pagamentoRules);
    }
}
