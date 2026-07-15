<?php

declare(strict_types=1);

namespace App\Modules\FormaPagamento\Requests;

use App\Enums\TipoFormaPagamento;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvarFormaPagamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? $this->user()->can('forma_pagamento.criar')
            : $this->user()->can('forma_pagamento.editar');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:100'],
            'tipo' => ['required', Rule::enum(TipoFormaPagamento::class)],
            'ativo' => ['nullable', 'boolean'],
            'gera_recebivel' => ['nullable', 'boolean'],
            'dias_liquidacao' => ['nullable', 'integer', 'min:0', 'max:365'],
            'taxa_percentual' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'permite_parcelas' => ['nullable', 'boolean'],
            'max_parcelas' => ['nullable', 'integer', 'min:1', 'max:60'],
            'taxas' => ['nullable', 'array'],
            'taxas.*.parcela_min' => ['required_with:taxas', 'integer', 'min:1', 'max:60'],
            'taxas.*.parcela_max' => ['required_with:taxas', 'integer', 'min:1', 'max:60'],
            'taxas.*.taxa_percentual' => ['required_with:taxas', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $faixas = collect($this->input('taxas', []))
                ->map(fn ($t) => [
                    'min' => (int) ($t['parcela_min'] ?? 0),
                    'max' => (int) ($t['parcela_max'] ?? 0),
                ])
                ->filter(fn ($f) => $f['min'] > 0 && $f['max'] > 0)
                ->values();

            foreach ($faixas as $i => $f) {
                if ($f['min'] > $f['max']) {
                    $validator->errors()->add("taxas.{$i}.parcela_min", 'O mínimo da faixa não pode ser maior que o máximo.');
                }
            }

            // Faixas não podem se sobrepor.
            $ordenadas = $faixas->sortBy('min')->values();
            for ($i = 1; $i < $ordenadas->count(); $i++) {
                if ($ordenadas[$i]['min'] <= $ordenadas[$i - 1]['max']) {
                    $validator->errors()->add('taxas', 'As faixas de parcelas não podem se sobrepor.');
                    break;
                }
            }

            // Faixa não pode exceder o máximo de parcelas permitido.
            $max = (int) $this->input('max_parcelas', 0);
            if ($max > 0 && $faixas->max('max') > $max) {
                $validator->errors()->add('taxas', "As faixas não podem exceder o máximo de {$max} parcelas.");
            }
        });
    }
}
