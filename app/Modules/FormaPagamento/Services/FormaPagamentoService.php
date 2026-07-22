<?php

declare(strict_types=1);

namespace App\Modules\FormaPagamento\Services;

use App\Enums\TipoFormaPagamento;
use App\Modules\Conta\Models\Conta;
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
        $query = FormaPagamento::withCount('taxas')->with('empresa:id,nome')->orderBy('nome');

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
    public function criar(FormaPagamentoData $dados, array $taxas, int $empresaId): FormaPagamento
    {
        return DB::transaction(function () use ($dados, $taxas, $empresaId) {
            $attrs = $this->normalizarPorTipo($dados);
            $attrs['empresa_id'] = $empresaId;

            $forma = FormaPagamento::create($attrs);
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
     * Cria as formas de pagamento padrão de uma empresa recém-criada.
     * Dinheiro/Pix entram no caixa; débito/crédito viram recebível (D+N, taxa).
     * Chamado no nascimento da empresa (CriarEmpresaAction) e no seeder de dev.
     * rede_id/empresa_id explícitos — o EmpresaTrait respeita quando já setados.
     */
    public function semearPadrao(int $redeId, int $empresaId): void
    {
        $base = ['rede_id' => $redeId, 'empresa_id' => $empresaId];

        // Conta bancaria padrao (destino de cartao/pix). semearPadrao das contas roda
        // antes (CriarEmpresaAction / seeders); se ausente, fica null e o motor resolve
        // pela natureza (fallback) — cartao/pix so exigem conta na tela do lojista.
        $contaRecebivelId = Conta::withoutGlobalScope('empresa')
            ->where('empresa_id', $empresaId)
            ->where('eh_destino_recebivel_padrao', true)
            ->value('id');

        FormaPagamento::create($base + [
            'nome' => 'Dinheiro',
            'tipo' => TipoFormaPagamento::Dinheiro,
            'gera_recebivel' => false,
            'dias_liquidacao' => 0,
            'taxa_percentual' => 0,
        ]);

        FormaPagamento::create($base + [
            'nome' => 'Pix',
            'tipo' => TipoFormaPagamento::Pix,
            'gera_recebivel' => false,
            'dias_liquidacao' => 0,
            'taxa_percentual' => 0,
            'conta_destino_id' => $contaRecebivelId, // Pix direto: cai no banco (D+0)
        ]);

        FormaPagamento::create($base + [
            'nome' => 'Cartão de Débito',
            'tipo' => TipoFormaPagamento::CartaoDebito,
            'gera_recebivel' => true,
            'dias_liquidacao' => 1,
            'taxa_percentual' => 1.99,
            'conta_destino_id' => $contaRecebivelId,
        ]);

        $credito = FormaPagamento::create($base + [
            'nome' => 'Cartão de Crédito',
            'tipo' => TipoFormaPagamento::CartaoCredito,
            'gera_recebivel' => true,
            'dias_liquidacao' => 30,
            'taxa_percentual' => 3.20,
            'permite_parcelas' => true,
            'max_parcelas' => 12,
            'conta_destino_id' => $contaRecebivelId,
        ]);

        foreach ([
            ['parcela_min' => 1, 'parcela_max' => 1, 'taxa_percentual' => 3.20],
            ['parcela_min' => 2, 'parcela_max' => 6, 'taxa_percentual' => 3.80],
            ['parcela_min' => 7, 'parcela_max' => 12, 'taxa_percentual' => 4.50],
        ] as $faixa) {
            $credito->taxas()->create($base + $faixa);
        }

        // Crediário: a loja financia o cliente em até N parcelas (a receber do cliente).
        FormaPagamento::create($base + [
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
     * (ou um POST forjado) tente. `permite_parcelas` é DERIVADO do tipo; `gera_recebivel` é derivado
     * do tipo, EXCETO no PIX (recebivelConfiguravel), em que o lojista escolhe: direto ao banco
     * (imediato) ou via maquineta/adquirente (recebível D+N com taxa).
     *
     * @return array<string, mixed>
     */
    private function normalizarPorTipo(FormaPagamentoData $dados): array
    {
        $tipo = $dados->tipo;
        $attrs = $dados->toArray();

        // gera_recebivel: configurável só onde o tipo permite (PIX); senão travado pelo tipo.
        $geraRecebivel = $tipo->recebivelConfiguravel()
            ? $dados->gera_recebivel
            : $tipo->geraRecebivelPadrao();

        $attrs['gera_recebivel'] = $geraRecebivel;
        $attrs['permite_parcelas'] = $tipo->permiteParcelasPadrao();

        // Liquidação (D+N) e taxa plana só valem quando o tipo os usa E o dinheiro é diferido
        // (recebível). PIX direto ao banco (gera_recebivel=false) volta a D+0 sem taxa.
        if (! $tipo->usaLiquidacao() || ! $geraRecebivel) {
            $attrs['dias_liquidacao'] = 0;
        }

        if (! $tipo->usaTaxaPlana() || ! $geraRecebivel) {
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
                'empresa_id' => $forma->empresa_id,
                'parcela_min' => (int) $t['parcela_min'],
                'parcela_max' => (int) $t['parcela_max'],
                'taxa_percentual' => (float) $t['taxa_percentual'],
            ]);
        }
    }
}
