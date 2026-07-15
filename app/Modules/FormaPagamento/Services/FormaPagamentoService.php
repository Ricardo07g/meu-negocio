<?php

declare(strict_types=1);

namespace App\Modules\FormaPagamento\Services;

use App\Enums\TipoFormaPagamento;
use App\Modules\FormaPagamento\DTOs\FormaPagamentoData;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FormaPagamentoService
{
    /**
     * @return Collection<int, FormaPagamento>
     */
    public function listar(array $filtros = []): Collection
    {
        $query = FormaPagamento::withCount('taxas')->orderBy('nome');

        if (! empty($filtros['q'])) {
            $query->where('nome', 'like', '%'.$filtros['q'].'%');
        }

        if (isset($filtros['ativo']) && $filtros['ativo'] !== '') {
            $query->where('ativo', (bool) $filtros['ativo']);
        }

        if (! empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        return $query->get();
    }

    /**
     * @param  array<int, array{parcela_min: mixed, parcela_max: mixed, taxa_percentual: mixed}>  $taxas
     */
    public function criar(FormaPagamentoData $dados, array $taxas = []): FormaPagamento
    {
        return DB::transaction(function () use ($dados, $taxas) {
            $forma = FormaPagamento::create($this->normalizarPorTipo($dados));
            $this->sincronizarTaxas($forma, $dados->tipo->usaFaixas() ? $taxas : []);

            return $forma->fresh('taxas');
        });
    }

    /**
     * @param  array<int, array{parcela_min: mixed, parcela_max: mixed, taxa_percentual: mixed}>  $taxas
     */
    public function atualizar(FormaPagamento $forma, FormaPagamentoData $dados, array $taxas = []): FormaPagamento
    {
        return DB::transaction(function () use ($forma, $dados, $taxas) {
            $forma->update($this->normalizarPorTipo($dados));
            $this->sincronizarTaxas($forma, $dados->tipo->usaFaixas() ? $taxas : []);

            return $forma->fresh('taxas');
        });
    }

    public function excluir(FormaPagamento $forma): void
    {
        // Soft delete: baixas historicas apontam para o id; nunca force delete.
        $forma->delete();
    }

    /**
     * Cria as formas de pagamento padrão de uma rede recém-criada.
     * Dinheiro/Pix entram no caixa; débito/crédito viram recebível (D+N, taxa).
     * Chamado no registro (RedeService) e no seeder de desenvolvimento.
     */
    public function semearPadrao(int $redeId): void
    {
        FormaPagamento::create([
            'rede_id' => $redeId,
            'nome' => 'Dinheiro',
            'tipo' => TipoFormaPagamento::Dinheiro,
            'gera_recebivel' => false,
            'dias_liquidacao' => 0,
            'taxa_percentual' => 0,
        ]);

        FormaPagamento::create([
            'rede_id' => $redeId,
            'nome' => 'Pix',
            'tipo' => TipoFormaPagamento::Pix,
            'gera_recebivel' => false,
            'dias_liquidacao' => 0,
            'taxa_percentual' => 0,
        ]);

        FormaPagamento::create([
            'rede_id' => $redeId,
            'nome' => 'Cartão de Débito',
            'tipo' => TipoFormaPagamento::CartaoDebito,
            'gera_recebivel' => true,
            'dias_liquidacao' => 1,
            'taxa_percentual' => 1.99,
        ]);

        $credito = FormaPagamento::create([
            'rede_id' => $redeId,
            'nome' => 'Cartão de Crédito',
            'tipo' => TipoFormaPagamento::CartaoCredito,
            'gera_recebivel' => true,
            'dias_liquidacao' => 30,
            'taxa_percentual' => 3.20,
            'permite_parcelas' => true,
            'max_parcelas' => 12,
        ]);

        foreach ([
            ['parcela_min' => 1, 'parcela_max' => 1, 'taxa_percentual' => 3.20],
            ['parcela_min' => 2, 'parcela_max' => 6, 'taxa_percentual' => 3.80],
            ['parcela_min' => 7, 'parcela_max' => 12, 'taxa_percentual' => 4.50],
        ] as $faixa) {
            $credito->taxas()->create(['rede_id' => $redeId] + $faixa);
        }

        // Crediário: a loja financia o cliente em até N parcelas (a receber do cliente).
        FormaPagamento::create([
            'rede_id' => $redeId,
            'nome' => 'Crediário',
            'tipo' => TipoFormaPagamento::Crediario,
            'gera_recebivel' => false,
            'dias_liquidacao' => 0,
            'taxa_percentual' => 0,
            'max_parcelas' => 12,
        ]);
    }

    /**
     * Força os campos por tipo: um "Dinheiro" nunca guarda taxa/recebível/parcelas, mesmo que a UI
     * (ou um POST forjado) tente. `gera_recebivel` e `permite_parcelas` são DERIVADOS do tipo.
     *
     * @return array<string, mixed>
     */
    private function normalizarPorTipo(FormaPagamentoData $dados): array
    {
        $tipo = $dados->tipo;
        $attrs = $dados->toArray();

        // Comportamento intrínseco ao tipo — não é escolha do lojista.
        $attrs['gera_recebivel'] = $tipo->geraRecebivelPadrao();
        $attrs['permite_parcelas'] = $tipo->permiteParcelasPadrao();

        if (! $tipo->usaLiquidacao()) {
            $attrs['dias_liquidacao'] = 0;
        }

        if (! $tipo->usaTaxaPlana()) {
            $attrs['taxa_percentual'] = 0;
        }

        if (! $tipo->usaAntecipacao()) {
            $attrs['antecipacao_automatica'] = false;
            $attrs['taxa_antecipacao_mensal'] = 0;
        }

        // max_parcelas só faz sentido no crédito (parcelas do cartão) e no crediário (parcelas do cliente).
        if (! $tipo->usaFaixas() && ! $tipo->ehCrediario()) {
            $attrs['max_parcelas'] = null;
        }

        return $attrs;
    }

    /**
     * @param  array<int, array{parcela_min: mixed, parcela_max: mixed, taxa_percentual: mixed}>  $taxas
     */
    private function sincronizarTaxas(FormaPagamento $forma, array $taxas): void
    {
        $forma->taxas()->delete();

        foreach ($taxas as $t) {
            if (empty($t['parcela_min']) || empty($t['parcela_max'])) {
                continue;
            }

            $forma->taxas()->create([
                'rede_id' => $forma->rede_id,
                'parcela_min' => (int) $t['parcela_min'],
                'parcela_max' => (int) $t['parcela_max'],
                'taxa_percentual' => (float) $t['taxa_percentual'],
            ]);
        }
    }
}
