<?php

namespace App\Modules\Despesa\Requests;

use App\Enums\CondicaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\FormaRecebimentoPrazo;
use App\Support\Parcelamento\CalculadoraParcelas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Despesa nao pode ser editada apos lancada — apenas criada (POST), cancelada
 * ou excluida. Este request cobre apenas o caso de criacao.
 */
class SalvarDespesaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('despesa.criar');
    }

    public function rules(): array
    {
        $empresasAtuais = (array) session('empresas_atuais', []);

        $condicoesParceladas = [CondicaoPagamento::APrazo->value];
        $condicoesHabilitadas = [
            CondicaoPagamento::AVista->value,
            CondicaoPagamento::APrazo->value,
        ];
        $condicoesComForma = $condicoesHabilitadas;

        return [
            'nome' => ['required', 'string', 'max:200'],
            'categoria_despesa_id' => ['nullable', 'integer', 'exists:categorias_despesa,id'],
            'fornecedor_nome' => ['nullable', 'string', 'max:150'],
            'documento' => ['nullable', 'string', 'max:80'],
            'observacoes' => ['nullable', 'string'],
            'mes_referencia' => ['required', 'date'],
            'data_emissao' => ['required', 'date'],
            'empresa_id' => array_filter([
                'nullable',
                'integer',
                $empresasAtuais !== [] ? 'in:'.implode(',', $empresasAtuais) : null,
            ]),
            'valor_total' => ['required', 'numeric', 'min:0.01'],
            'condicao_pagamento' => ['required', Rule::in($condicoesHabilitadas)],
            'primeiro_vencimento' => ['required', 'date', 'after_or_equal:data_emissao'],
            'forma_pagamento' => [
                'required_if:condicao_pagamento,'.implode(',', $condicoesComForma),
                'nullable',
                Rule::enum(FormaPagamento::class),
            ],
            'numero_parcelas' => [
                'required_if:condicao_pagamento,'.implode(',', $condicoesParceladas),
                'nullable',
                'integer',
                'min:2',
                'max:'.CalculadoraParcelas::MAX_PARCELAS,
            ],
            'forma_recebimento_prazo' => [
                'required_if:condicao_pagamento,'.implode(',', $condicoesParceladas),
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
    }
}
