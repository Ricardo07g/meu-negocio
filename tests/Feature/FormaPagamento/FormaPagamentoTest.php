<?php

declare(strict_types=1);

namespace Tests\Feature\FormaPagamento;

use App\Enums\TipoFormaPagamento;
use App\Modules\Conta\Models\Conta;
use App\Modules\Conta\Services\ContaService;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\FormaPagamento\Services\FormaPagamentoService;
use App\Modules\Tenant\Models\Empresa;
use Database\Factories\{PagamentoFactory, ParcelaPagamentoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Formas de pagamento por empresa: CRUD, validação das faixas de taxa e
 * isolamento multi-tenant (não se pode baixar com forma de outra rede nem
 * acessar forma de outra empresa da mesma rede).
 */
class FormaPagamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_empresa_nasce_com_formas_padrao(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $formas = FormaPagamento::where('empresa_id', $contexto['empresa']->id)->get();

        // Dinheiro, Pix, Cartão de Débito, Cartão de Crédito, Crediário.
        $this->assertCount(5, $formas, 'Empresa deveria nascer com 5 formas padrão.');
        $this->assertNotNull(
            $formas->firstWhere('tipo', TipoFormaPagamento::Crediario),
            'Deveria existir a forma Crediário.'
        );
    }

    public function test_nao_acessa_forma_de_outra_empresa_da_mesma_rede(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        // Segunda empresa na MESMA rede, com suas próprias formas.
        $empB = Empresa::create(['rede_id' => $rede->id, 'nome' => 'Filial B']);
        app(FormaPagamentoService::class)->semearPadrao($rede->id, $empB->id);

        $formaB = FormaPagamento::withoutGlobalScopes()
            ->where('empresa_id', $empB->id)
            ->firstOrFail();

        // O usuário opera na empresa A (contexto padrão); a forma da B some do escopo.
        $this->get(route('formas-pagamento.edit', $formaB))->assertNotFound();
    }

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
            'conta_destino_id' => $this->contaBancoId(),
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

    public function test_pix_via_maquineta_configura_recebivel_liquidacao_e_taxa(): void
    {
        $this->criarRedeAutenticada();

        // PIX na maquineta: o lojista liga "gera recebível" e informa D+N e taxa.
        $resp = $this->post(route('formas-pagamento.store'), [
            'nome' => 'Pix Maquineta',
            'tipo' => TipoFormaPagamento::Pix->value,
            'ativo' => 1,
            'gera_recebivel' => 1,
            'dias_liquidacao' => 1,
            'taxa_percentual' => 0.99,
            'conta_destino_id' => $this->contaBancoId(),
        ]);

        $resp->assertRedirect(route('formas-pagamento.index'));

        $forma = FormaPagamento::where('nome', 'Pix Maquineta')->firstOrFail();
        $this->assertTrue($forma->gera_recebivel, 'PIX maquineta gera recebível do adquirente.');
        $this->assertSame(1, $forma->dias_liquidacao);
        $this->assertSame(0.99, (float) $forma->taxa_percentual);
    }

    public function test_pix_direto_zera_liquidacao_e_taxa(): void
    {
        $this->criarRedeAutenticada();

        // PIX direto ao banco: mesmo enviando D+N e taxa, o servidor zera (cai na hora, sem taxa).
        $resp = $this->post(route('formas-pagamento.store'), [
            'nome' => 'Pix Direto',
            'tipo' => TipoFormaPagamento::Pix->value,
            'ativo' => 1,
            'gera_recebivel' => 0,
            'dias_liquidacao' => 5,
            'taxa_percentual' => 2.0,
            'conta_destino_id' => $this->contaBancoId(),
        ]);

        $resp->assertRedirect(route('formas-pagamento.index'));

        $forma = FormaPagamento::where('nome', 'Pix Direto')->firstOrFail();
        $this->assertFalse($forma->gera_recebivel, 'PIX direto não gera recebível.');
        $this->assertSame(0, $forma->dias_liquidacao, 'PIX direto é imediato (D+0).');
        $this->assertSame(0.0, (float) $forma->taxa_percentual, 'PIX direto não tem taxa.');
    }

    public function test_forma_aceita_conta_destino_da_propria_empresa(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $contaBanco = Conta::where('empresa_id', $contexto['empresa']->id)
            ->where('eh_destino_recebivel_padrao', true)
            ->firstOrFail();

        $resp = $this->post(route('formas-pagamento.store'), [
            'nome' => 'Pix Nubank',
            'tipo' => TipoFormaPagamento::Pix->value,
            'ativo' => 1,
            'conta_destino_id' => $contaBanco->id,
        ]);

        $resp->assertRedirect(route('formas-pagamento.index'));

        $forma = FormaPagamento::where('nome', 'Pix Nubank')->firstOrFail();
        $this->assertSame($contaBanco->id, $forma->conta_destino_id);
    }

    public function test_conta_destino_de_outra_empresa_e_rejeitada(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // Segunda empresa na MESMA rede, com suas próprias contas.
        $empB = Empresa::create(['rede_id' => $contexto['rede']->id, 'nome' => 'Filial B']);
        app(ContaService::class)->semearPadrao($contexto['rede']->id, $empB->id);

        $contaB = Conta::withoutGlobalScopes()->where('empresa_id', $empB->id)->firstOrFail();

        // O usuário opera na empresa A: apontar para a conta da B viola o tenancy.
        $resp = $this->post(route('formas-pagamento.store'), [
            'nome' => 'Pix Vazado',
            'tipo' => TipoFormaPagamento::Pix->value,
            'ativo' => 1,
            'conta_destino_id' => $contaB->id,
        ]);

        $resp->assertSessionHasErrors('conta_destino_id');
        $this->assertDatabaseMissing('formas_pagamento', ['nome' => 'Pix Vazado']);
    }

    public function test_cartao_e_pix_exigem_conta_destino(): void
    {
        $this->criarRedeAutenticada();

        foreach ([TipoFormaPagamento::CartaoCredito, TipoFormaPagamento::CartaoDebito, TipoFormaPagamento::Pix] as $tipo) {
            $this->post(route('formas-pagamento.store'), [
                'nome' => 'Sem Conta '.$tipo->value,
                'tipo' => $tipo->value,
                'ativo' => 1,
            ])->assertSessionHasErrors('conta_destino_id');
        }

        $this->assertDatabaseMissing('formas_pagamento', ['nome' => 'Sem Conta pix']);
    }

    public function test_dinheiro_nao_exige_conta_destino(): void
    {
        $this->criarRedeAutenticada();

        $this->post(route('formas-pagamento.store'), [
            'nome' => 'Dinheiro Balcão',
            'tipo' => TipoFormaPagamento::Dinheiro->value,
            'ativo' => 1,
        ])->assertRedirect(route('formas-pagamento.index'));

        $forma = FormaPagamento::where('nome', 'Dinheiro Balcão')->firstOrFail();
        $this->assertNull($forma->conta_destino_id, 'Dinheiro cai no caixa por natureza (conta opcional).');
    }

    public function test_cartao_nao_aceita_conta_caixa(): void
    {
        $this->criarRedeAutenticada();

        $this->post(route('formas-pagamento.store'), [
            'nome' => 'Débito na Gaveta',
            'tipo' => TipoFormaPagamento::CartaoDebito->value,
            'ativo' => 1,
            'gera_recebivel' => 1,
            'conta_destino_id' => $this->contaCaixaId(), // cartao nunca cai na gaveta
        ])->assertSessionHasErrors('conta_destino_id');

        $this->assertDatabaseMissing('formas_pagamento', ['nome' => 'Débito na Gaveta']);
    }

    public function test_formas_semeadas_de_cartao_e_pix_ligam_a_conta_bancaria(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $bancoId = $this->contaBancoId();

        $comConta = FormaPagamento::where('empresa_id', $contexto['empresa']->id)
            ->whereIn('tipo', [
                TipoFormaPagamento::CartaoCredito,
                TipoFormaPagamento::CartaoDebito,
                TipoFormaPagamento::Pix,
            ])->get();

        foreach ($comConta as $forma) {
            $this->assertSame($bancoId, $forma->conta_destino_id, "{$forma->nome} deveria cair na conta bancária.");
        }

        // Dinheiro/Crediário continuam sem conta (caem no caixa por natureza).
        $dinheiro = FormaPagamento::where('empresa_id', $contexto['empresa']->id)
            ->where('tipo', TipoFormaPagamento::Dinheiro)->firstOrFail();
        $this->assertNull($dinheiro->conta_destino_id);
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

    /** Conta bancária padrão (destino de recebível) da empresa em contexto. */
    private function contaBancoId(): int
    {
        return (int) Conta::where('eh_destino_recebivel_padrao', true)->value('id');
    }

    /** Conta Caixa (gaveta) da empresa em contexto. */
    private function contaCaixaId(): int
    {
        return (int) Conta::where('eh_caixa_padrao', true)->value('id');
    }
}
