<?php

declare(strict_types=1);

namespace App\Modules\Venda\Requests;

use App\Enums\FormaRecebimentoPrazo;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\Servico\Models\Servico;
use App\Support\Parcelamento\CalculadoraParcelas;
use App\Support\Venda\TotalVenda;
use Illuminate\Contracts\Validation\Validator;
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

        // ME-010 v3: empresa vem do contexto da listagem (URL `?empresa_id=X`)
        // ou do header (`empresas_atuais`). Form NAO precisa enviar; se enviar,
        // validamos contra `empresas_atuais` para defesa em profundidade.
        $empresasAtuais = $this->empresasAtuais();
        $regrasEmpresa = [
            'empresa_id' => array_filter([
                'nullable',
                'integer',
                $empresasAtuais !== [] ? 'in:'.implode(',', $empresasAtuais) : null,
            ]),
        ];

        // Forma e empresa-level: aceita so formas de rede + empresa acessivel.
        // O gate preciso (forma pertence a empresa da venda) e o findOrFail +
        // abort_unless no controller (extrairRecebimentos).
        $formaAcessivel = Rule::exists('formas_pagamento', 'id')
            ->whereNull('deleted_at')
            ->where('rede_id', $this->user()->rede_id);
        if ($empresasAtuais !== []) {
            $formaAcessivel->whereIn('empresa_id', $empresasAtuais);
        }

        // Recebimentos: N linhas (forma + valor). A soma == total e a
        // obrigatoriedade dos campos de crediario sao checadas em withValidator().
        $pagamentoRules = [
            'mes_referencia' => ['required', 'date'],
            'recebimentos' => ['required', 'array', 'min:1'],
            'recebimentos.*.forma_pagamento_id' => ['required', 'integer', $formaAcessivel],
            'recebimentos.*.valor' => ['required', 'numeric', 'min:0.01'],
            'recebimentos.*.parcelas_cartao' => ['nullable', 'integer', 'min:1', 'max:'.CalculadoraParcelas::MAX_PARCELAS],
            // Campos do carne (crediario): required condicional em withValidator().
            'numero_parcelas' => ['nullable', 'integer', 'min:2', 'max:'.CalculadoraParcelas::MAX_PARCELAS],
            'primeiro_vencimento' => ['nullable', 'date', 'after_or_equal:today'],
            'forma_recebimento_prazo' => ['nullable', Rule::enum(FormaRecebimentoPrazo::class)],
            'parcelas' => ['nullable', 'array'],
            'parcelas.*.numero' => ['required_with:parcelas', 'integer', 'min:1'],
            'parcelas.*.total' => ['required_with:parcelas', 'integer', 'min:1'],
            'parcelas.*.valor' => ['required_with:parcelas', 'numeric', 'min:0.01'],
            'parcelas.*.data_vencimento' => ['required_with:parcelas', 'date'],
            'parcelas.*.mes_referencia' => ['required_with:parcelas', 'date'],
        ];

        if ($tipoVenda === 'produto') {
            return array_merge($regrasEmpresa, [
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
        $isEtapas = $servico && $servico->isEtapas();

        $rules = [
            'tipo_venda' => ['required', 'in:servico,produto'],
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'servico_id' => ['required', 'integer', 'exists:servicos,id'],
            'atendente_id' => ['required', 'integer', 'exists:usuarios,id'],
        ];

        if ($isEtapas) {
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

        return array_merge($regrasEmpresa, $rules, $pagamentoRules);
    }

    /**
     * Validacoes cruzadas dos recebimentos (data-driven, ja que nao ha mais
     * toggle a-vista/a-prazo): a soma bate com o total; crediario e single-line
     * e exige os dados do carne.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $linhas = $this->input('recebimentos');
            if (! is_array($linhas) || $linhas === []) {
                return;
            }

            // 1) Soma dos recebimentos deve bater com o total da venda.
            $total = $this->totalDaVenda();
            if ($total !== null) {
                $soma = array_sum(array_map(fn ($l) => (float) ($l['valor'] ?? 0), $linhas));
                if (abs($soma - $total) > 0.01) {
                    $v->errors()->add(
                        'recebimentos',
                        sprintf(
                            'A soma dos recebimentos (R$ %s) nao bate com o total da venda (R$ %s).',
                            number_format($soma, 2, ',', '.'),
                            number_format($total, 2, ',', '.'),
                        ),
                    );
                }
            }

            // 2) Resolve as formas escopadas (rede + empresas acessiveis) para
            // saber quais forcam "a prazo" (crediario).
            $ids = array_filter(array_map(fn ($l) => (int) ($l['forma_pagamento_id'] ?? 0), $linhas));
            $formas = FormaPagamento::query()
                ->where('rede_id', $this->user()->rede_id)
                ->when($this->empresasAtuais() !== [], fn ($q) => $q->whereIn('empresa_id', $this->empresasAtuais()))
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            $temCrediario = collect($linhas)->contains(
                fn ($l) => $formas->get((int) ($l['forma_pagamento_id'] ?? 0))?->tipo->forcaAPrazo() === true,
            );

            // 3) Crediario (a loja financia o cliente) nao combina com outras
            // formas na mesma venda (v1: split imediato e crediario sao modos distintos).
            if ($temCrediario && count($linhas) > 1) {
                $v->errors()->add(
                    'recebimentos',
                    'Crediario nao pode ser combinado com outras formas de recebimento na mesma venda.',
                );
            }

            // 4) Crediario exige os dados do carne.
            if ($temCrediario) {
                if (! $this->filled('numero_parcelas')) {
                    $v->errors()->add('numero_parcelas', 'Informe o numero de parcelas do crediario.');
                }
                if (! $this->filled('primeiro_vencimento')) {
                    $v->errors()->add('primeiro_vencimento', 'Informe o primeiro vencimento do crediario.');
                }
                if (! $this->filled('forma_recebimento_prazo')) {
                    $v->errors()->add('forma_recebimento_prazo', 'Informe a forma de recebimento do crediario.');
                }
            }
        });
    }

    /**
     * Universo de empresas acessiveis (para escopar a validacao da forma).
     *
     * @return array<int, int>
     */
    private function empresasAtuais(): array
    {
        return (array) session('empresas_atuais', []);
    }

    /**
     * Total da venda conforme o tipo, para comparar com a soma dos recebimentos.
     * Retorna null quando ainda nao da para calcular (ex.: servico inexistente).
     */
    private function totalDaVenda(): ?float
    {
        if ($this->input('tipo_venda', 'servico') === 'produto') {
            return TotalVenda::deItens((array) $this->input('itens', []));
        }

        $servico = Servico::find($this->input('servico_id'));
        if (! $servico) {
            return null;
        }

        if ($servico->isEtapas()) {
            return $this->filled('valor_total') ? (float) $this->input('valor_total') : null;
        }

        return (float) $servico->valor;
    }
}
