<?php

declare(strict_types=1);

namespace App\Modules\FormaPagamento\Requests;

use App\Enums\{TipoConta, TipoFormaPagamento};
use App\Modules\Conta\Models\Conta;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Support\ContextoEmpresa;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

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
            'antecipacao_automatica' => ['nullable', 'boolean'],
            'taxa_antecipacao_mensal' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Conta destino: obrigatoria em cartao/pix (cada maquineta cai numa conta); opcional
            // no resto. O `exists` cru ignora os global scopes — filtramos rede/empresa na mao
            // para nao aceitar conta de outra rede/empresa.
            'conta_destino_id' => [
                $this->contaDestinoObrigatoria() ? 'required' : 'nullable',
                'integer',
                $this->regraContaAcessivel(),
            ],
            'taxas' => ['nullable', 'array'],
            'taxas.*.parcela_min' => ['required_with:taxas', 'integer', 'min:1', 'max:60'],
            'taxas.*.parcela_max' => ['required_with:taxas', 'integer', 'min:1', 'max:60'],
            'taxas.*.taxa_percentual' => ['required_with:taxas', 'numeric', 'min:0', 'max:100'],
        ];
    }

    private function regraContaAcessivel(): Exists
    {
        // A conta destino tem de ser da MESMA empresa da forma (empresa-alvo),
        // nao apenas do universo acessivel — senao um Admin com N empresas poderia
        // apontar a forma da empresa A para uma conta da B. O `exists` cru ignora
        // os global scopes, por isso filtramos rede + empresa na mao.
        $regra = Rule::exists('contas', 'id')
            ->whereNull('deleted_at')
            ->where('rede_id', $this->user()?->rede_id);

        $empresaAlvo = $this->empresaAlvo();
        if ($empresaAlvo !== null) {
            $regra->where('empresa_id', $empresaAlvo);
        } else {
            // Sem empresa resolvida (borda): mantem ao menos o universo acessivel.
            $empresas = array_values(array_map('intval', (array) session('empresas_atuais', [])));
            // Fail-closed: sem empresa-alvo nem universo, nenhuma conta e aceita (o controller
            // ja aborta antes de persistir; aqui garante que a validacao tambem falhe fechada).
            $regra->whereIn('empresa_id', $empresas !== [] ? $empresas : [0]);
        }

        return $regra;
    }

    /**
     * Empresa para a qual a forma esta sendo salva: na edicao, a da propria forma;
     * na criacao, a resolvida pelo contexto (espelha o FormaPagamentoController).
     */
    private function empresaAlvo(): ?int
    {
        $forma = $this->route('formas_pagamento');
        if ($forma instanceof FormaPagamento) {
            return (int) $forma->empresa_id;
        }

        $empresa = ContextoEmpresa::resolver() ?? $this->user()?->empresa_id;

        return $empresa !== null ? (int) $empresa : null;
    }

    /** Cartao (debito/credito) e Pix exigem conta destino explicita (nunca caem na gaveta). */
    private function contaDestinoObrigatoria(): bool
    {
        return TipoFormaPagamento::tryFrom((string) $this->input('tipo'))?->exigeContaDestino() ?? false;
    }

    public function messages(): array
    {
        return [
            'conta_destino_id.required' => 'Escolha a conta onde o dinheiro desta forma cai (banco ou carteira).',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validarContaDestinoNaoEhCaixa($validator);

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

    /**
     * Trilho: cartão e Pix nunca caem na gaveta do caixa — a conta destino deve ser
     * banco/carteira. Rede-scoped (RedeTrait), suficiente para ler o tipo da conta.
     */
    private function validarContaDestinoNaoEhCaixa(Validator $validator): void
    {
        $contaId = $this->input('conta_destino_id');
        if (! $contaId || ! $this->contaDestinoObrigatoria()) {
            return;
        }

        $conta = Conta::withoutGlobalScope('empresa')->find((int) $contaId);
        if ($conta && $conta->tipo === TipoConta::Caixa) {
            $validator->errors()->add('conta_destino_id', 'Cartão e Pix não caem na gaveta do caixa. Escolha uma conta bancária ou carteira.');
        }
    }
}
