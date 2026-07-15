<?php

declare(strict_types=1);

namespace Tests\Feature\FormaPagamento;

use App\Enums\TipoFormaPagamento;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use Database\Factories\{PagamentoFactory, ParcelaPagamentoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catálogo de formas de pagamento por rede: CRUD, validação das faixas de taxa
 * e isolamento multi-tenant (não se pode baixar com forma de outra rede).
 */
class FormaPagamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_telas_de_listagem_e_cadastro_renderizam(): void
    {
        $this->criarRedeAutenticada();

        $this->get(route('formas-pagamento.index'))
            ->assertOk()
            ->assertViewIs('formapagamento::index')
            ->assertSee('Cartão de Crédito'); // forma padrão semeada

        $this->get(route('formas-pagamento.create'))
            ->assertOk()
            ->assertSee('id="fp-faixas-card"', false); // repeater de faixas
    }

    public function test_cria_forma_de_credito_com_faixas_de_taxa(): void
    {
        $this->criarRedeAutenticada();

        $resp = $this->post(route('formas-pagamento.store'), [
            'nome' => 'Crédito Cielo',
            'tipo' => TipoFormaPagamento::CartaoCredito->value,
            'ativo' => 1,
            'gera_recebivel' => 1,
            'dias_liquidacao' => 30,
            'taxa_percentual' => 3.20,
            'permite_parcelas' => 1,
            'max_parcelas' => 12,
            'taxas' => [
                ['parcela_min' => 1, 'parcela_max' => 1, 'taxa_percentual' => 3.20],
                ['parcela_min' => 2, 'parcela_max' => 6, 'taxa_percentual' => 3.80],
                ['parcela_min' => 7, 'parcela_max' => 12, 'taxa_percentual' => 4.50],
            ],
        ]);

        $resp->assertRedirect(route('formas-pagamento.index'));
        $resp->assertSessionHas('sucesso');

        $forma = FormaPagamento::where('nome', 'Crédito Cielo')->firstOrFail();
        $this->assertTrue($forma->gera_recebivel);
        $this->assertSame(30, $forma->dias_liquidacao);
        $this->assertCount(3, $forma->taxas);
        $this->assertSame(3.80, $forma->taxaParaParcelas(4), 'Faixa 2-6 → 3,80%.');
        $this->assertSame(4.50, $forma->taxaParaParcelas(10), 'Faixa 7-12 → 4,50%.');
    }

    public function test_normaliza_campos_por_tipo_ao_salvar(): void
    {
        $this->criarRedeAutenticada();

        // "Dinheiro" com um monte de atributos que NÃO lhe pertencem: o servidor
        // deve zerá-los, mesmo que a UI (ou um POST forjado) os envie.
        $resp = $this->post(route('formas-pagamento.store'), [
            'nome' => 'Dinheiro Poluído',
            'tipo' => TipoFormaPagamento::Dinheiro->value,
            'ativo' => 1,
            'gera_recebivel' => 1,
            'dias_liquidacao' => 30,
            'taxa_percentual' => 5.5,
            'permite_parcelas' => 1,
            'max_parcelas' => 10,
            'antecipacao_automatica' => 1,
            'taxa_antecipacao_mensal' => 2.5,
            'taxas' => [
                ['parcela_min' => 1, 'parcela_max' => 6, 'taxa_percentual' => 3.0],
            ],
        ]);

        $resp->assertRedirect(route('formas-pagamento.index'));

        $forma = FormaPagamento::where('nome', 'Dinheiro Poluído')->firstOrFail();
        $this->assertFalse($forma->gera_recebivel, 'Dinheiro não gera recebível.');
        $this->assertSame(0, $forma->dias_liquidacao);
        $this->assertSame(0.0, (float) $forma->taxa_percentual);
        $this->assertFalse($forma->permite_parcelas);
        $this->assertNull($forma->max_parcelas);
        $this->assertFalse($forma->antecipacao_automatica);
        $this->assertSame(0.0, (float) $forma->taxa_antecipacao_mensal);
        $this->assertCount(0, $forma->taxas, 'Dinheiro não tem faixas de taxa.');
    }

    public function test_cria_crediario_com_teto_de_parcelas(): void
    {
        $this->criarRedeAutenticada();

        $resp = $this->post(route('formas-pagamento.store'), [
            'nome' => 'Crediário da Loja',
            'tipo' => TipoFormaPagamento::Crediario->value,
            'ativo' => 1,
            'max_parcelas' => 6,
        ]);

        $resp->assertRedirect(route('formas-pagamento.index'));

        $forma = FormaPagamento::where('nome', 'Crediário da Loja')->firstOrFail();
        $this->assertSame(TipoFormaPagamento::Crediario, $forma->tipo);
        $this->assertFalse($forma->gera_recebivel, 'Crediário é a receber do cliente, não do banco.');
        $this->assertSame(6, $forma->max_parcelas, 'Máx. de parcelas do cliente é preservado no crediário.');
    }

    public function test_faixas_sobrepostas_sao_rejeitadas(): void
    {
        $this->criarRedeAutenticada();

        $resp = $this->post(route('formas-pagamento.store'), [
            'nome' => 'Crédito Ruim',
            'tipo' => TipoFormaPagamento::CartaoCredito->value,
            'gera_recebivel' => 1,
            'permite_parcelas' => 1,
            'max_parcelas' => 12,
            'taxas' => [
                ['parcela_min' => 1, 'parcela_max' => 3, 'taxa_percentual' => 3.00],
                ['parcela_min' => 2, 'parcela_max' => 6, 'taxa_percentual' => 3.80],
            ],
        ]);

        $resp->assertSessionHasErrors('taxas');
        $this->assertDatabaseMissing('formas_pagamento', ['nome' => 'Crédito Ruim']);
    }

    public function test_excluir_forma_faz_soft_delete(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $forma = $this->formaPagamento($contexto['rede'], TipoFormaPagamento::Dinheiro);

        $this->delete(route('formas-pagamento.destroy', $forma))
            ->assertRedirect(route('formas-pagamento.index'));

        $this->assertSoftDeleted('formas_pagamento', ['id' => $forma->id]);
    }

    public function test_baixa_com_forma_de_outra_rede_e_rejeitada(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // Parcela pendente na rede autenticada.
        $pagamento = PagamentoFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'valor_total' => 100.00,
        ]);
        $parcela = ParcelaPagamentoFactory::new()->pendente()->create([
            'pagamento_id' => $pagamento->id,
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'valor' => 100.00,
        ]);

        // Forma de OUTRA rede (buraco de tenancy do `exists` cru).
        $outra = $this->criarRede('outra');
        $formaOutraRede = $this->formaPagamento($outra['rede'], TipoFormaPagamento::Dinheiro);

        $resp = $this->post(route('parcelas-pagamento.baixa', $parcela), [
            'valor' => 100.00,
            'forma_pagamento' => $formaOutraRede->id,
        ]);

        $resp->assertSessionHasErrors('forma_pagamento');
        $this->assertDatabaseCount('baixas_pagamento', 0);
    }
}
