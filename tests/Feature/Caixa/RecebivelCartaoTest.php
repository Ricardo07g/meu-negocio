<?php

declare(strict_types=1);

namespace Tests\Feature\Caixa;

use App\Enums\{CondicaoPagamento, StatusPagamento, StatusRecebivel, TipoFormaPagamento};
use App\Modules\Caixa\Models\{MovimentoCaixa, Recebivel};
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Produto\Models\Produto;
use App\Modules\Venda\Models\VendaProduto;
use App\Modules\Venda\Services\VendaService;
use Carbon\Carbon;
use Database\Factories\RecebivelFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Conceito central da fase: venda no cartão quita o cliente na hora mas NÃO
 * entra na gaveta do caixa — vira recebível do adquirente (D+N, líquido de
 * taxa). Cobre geração, taxa por faixa, ausência de movimento de caixa,
 * estorno (cancela recebíveis) e o status computado por data.
 */
class RecebivelCartaoTest extends TestCase
{
    use RefreshDatabase;

    private function venderNoCredito(array $contexto, float $valor, int $parcelasCartao): VendaProduto
    {
        $produto = Produto::create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Combo',
            'valor_venda' => $valor,
            'valor_custo' => $valor / 2,
            'quantidade' => 10,
            'ativo' => true,
        ]);

        return app(VendaService::class)->criarVendaProduto(
            cliente_id: null,
            itens: [[
                'produto_id' => $produto->id,
                'quantidade' => 1,
                'valor_unitario' => $valor,
                'desconto' => 0,
                'acrescimo' => 0,
            ]],
            condicao: CondicaoPagamento::AVista,
            mesReferencia: Carbon::now()->startOfMonth(),
            formaAvista: $this->formaPagamento($contexto['rede'], TipoFormaPagamento::CartaoCredito),
            parcelasCartao: $parcelasCartao,
        );
    }

    public function test_venda_cartao_credito_gera_recebiveis_sem_tocar_o_caixa(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // Sem caixa aberto de propósito: cartão não deve exigir caixa.
        $this->venderNoCredito($contexto, 300.00, 3);

        $pagamento = Pagamento::with('parcelas')->latest('id')->firstOrFail();

        // Cliente quitado: nada em contas a receber.
        $this->assertSame(StatusPagamento::Pago, $pagamento->status, 'Cliente deveria estar quitado no cartão.');
        $this->assertCount(1, $pagamento->parcelas, 'Cartão não gera parcelas do cliente.');

        // Nenhum movimento no caixa (o dinheiro não entrou na gaveta).
        $this->assertSame(0, MovimentoCaixa::count(), 'Cartão não deve gerar movimento de caixa.');

        // 3 recebíveis do adquirente, D+30/60/90, líquidos da taxa da faixa 2-6 (3,80%).
        $recebiveis = Recebivel::orderBy('parcela_numero')->get();
        $this->assertCount(3, $recebiveis, 'Crédito 3x deveria gerar 3 recebíveis.');

        foreach ($recebiveis as $r) {
            $this->assertSame(3.80, (float) $r->taxa_percentual, 'A faixa 2-6 parcelas cobra 3,80%.');
        }

        // Líquido total = 300 * (1 - 3.80/100) = 288,60.
        $this->assertEqualsWithDelta(288.60, (float) $recebiveis->sum('valor_liquido'), 0.01);
        $this->assertEqualsWithDelta(300.00, (float) $recebiveis->sum('valor_bruto'), 0.01);

        // Datas previstas mensais a partir de D+30.
        $this->assertTrue(
            $recebiveis[0]->data_prevista->betweenIncluded(now()->addDays(29), now()->addDays(31)),
            'A primeira previsão deveria ser ~D+30.'
        );
    }

    public function test_venda_cartao_debito_gera_um_recebivel_em_d1(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $produto = Produto::create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Item',
            'valor_venda' => 100.00,
            'valor_custo' => 40.00,
            'quantidade' => 5,
            'ativo' => true,
        ]);

        app(VendaService::class)->criarVendaProduto(
            cliente_id: null,
            itens: [[
                'produto_id' => $produto->id,
                'quantidade' => 1,
                'valor_unitario' => 100.00,
                'desconto' => 0,
                'acrescimo' => 0,
            ]],
            condicao: CondicaoPagamento::AVista,
            mesReferencia: Carbon::now()->startOfMonth(),
            formaAvista: $this->formaPagamento($contexto['rede'], TipoFormaPagamento::CartaoDebito),
        );

        $recebiveis = Recebivel::get();
        $this->assertCount(1, $recebiveis, 'Débito gera 1 recebível.');
        $this->assertSame(1.99, (float) $recebiveis[0]->taxa_percentual);
        $this->assertEqualsWithDelta(98.01, (float) $recebiveis[0]->valor_liquido, 0.01, '100 - 1,99% = 98,01.');
        $this->assertSame(0, MovimentoCaixa::count());
    }

    public function test_cancelar_venda_no_cartao_cancela_recebiveis_sem_saida_no_caixa(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $venda = $this->venderNoCredito($contexto, 300.00, 3);

        $this->assertSame(3, Recebivel::ativos()->count(), 'Antes do estorno, 3 recebíveis ativos.');

        // Não deve estourar por caixa_id null (regressão do estorno de cartão).
        app(VendaService::class)->cancelarVendaProduto($venda);

        $this->assertSame(0, Recebivel::ativos()->count(), 'Todos os recebíveis deveriam ficar cancelados.');
        $this->assertSame(3, Recebivel::withoutGlobalScopes()->whereNotNull('cancelado_em')->count());
        $this->assertSame(0, MovimentoCaixa::count(), 'Estorno de cartão não gera saída no caixa.');
        $this->assertSame(StatusPagamento::Estornado, $venda->pagamento->fresh()->status);
    }

    public function test_status_do_recebivel_e_derivado_pela_data(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empresa = $contexto['empresa'];
        $forma = $this->formaPagamento($rede, TipoFormaPagamento::CartaoCredito);

        $base = ['rede_id' => $rede->id, 'empresa_id' => $empresa->id, 'forma_pagamento_id' => $forma->id];

        $previsto = RecebivelFactory::new()->previsto()->create($base);
        $recebido = RecebivelFactory::new()->recebido()->create($base);
        $cancelado = RecebivelFactory::new()->cancelado()->create($base);

        $this->assertSame(StatusRecebivel::Previsto, $previsto->statusEfetivo());
        $this->assertSame(StatusRecebivel::Recebido, $recebido->statusEfetivo());
        $this->assertSame(StatusRecebivel::Cancelado, $cancelado->statusEfetivo());
    }
}
