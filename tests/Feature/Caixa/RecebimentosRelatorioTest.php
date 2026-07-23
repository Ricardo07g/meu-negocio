<?php

declare(strict_types=1);

namespace Tests\Feature\Caixa;

use App\Enums\TipoFormaPagamento;
use App\Modules\Caixa\Models\BaixaPagamento;
use App\Modules\Caixa\Services\ResumoDiaService;
use App\Modules\Conta\Models\Conta;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\Venda\Models\VendaProduto;
use App\Modules\Venda\Services\VendaService;
use Database\Factories\{CaixaFactory, ProdutoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * Relatório de recebimentos por período (ResumoDiaService::porPeriodo + tela
 * caixas/recebimentos): a casa coerente onde o lojista vê TODOS os recebimentos
 * por forma, inclusive os que não passam pela gaveta (pix/cartão) — no regime
 * "quando o cliente pagou" (ADR-0011), sem saldo de banco.
 */
class RecebimentosRelatorioTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    private function formaId(TipoFormaPagamento $tipo): int
    {
        return FormaPagamento::ativos()->where('tipo', $tipo->value)->firstOrFail()->id;
    }

    /** Cria uma venda à vista de R$100 com split dinheiro 50 + pix 30 + cartão 20 (hoje). */
    private function venderSplit(array $contexto): void
    {
        CaixaFactory::new()->aberto()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
        ]);
        $produto = ProdutoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'quantidade' => 20,
            'valor_venda' => 100.00,
        ]);

        $this->post(route('vendas.store'), [
            'tipo_venda' => 'produto',
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => 1,
                'valor_unitario' => 100.00,
                'desconto' => 0,
                'acrescimo' => 0,
            ]],
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Dinheiro), 'valor' => 50.00],
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Pix), 'valor' => 30.00],
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::CartaoCredito), 'valor' => 20.00],
            ],
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ])->assertSessionHas('sucesso');
    }

    public function test_resumo_por_periodo_agrupa_todas_as_formas(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->venderSplit($contexto);
        $hoje = today()->toDateString();

        $resumo = app(ResumoDiaService::class)->porPeriodo($hoje, $hoje);

        $this->assertSame(100.00, $resumo['totalRecebido']);
        $this->assertSame(0.0, $resumo['totalEstornado']);
        $this->assertSame(100.00, $resumo['liquido']);

        $porForma = collect($resumo['linhas'])->pluck('recebido', 'forma');
        $this->assertSame(50.00, $porForma['Dinheiro']);
        $this->assertSame(30.00, $porForma['Pix']);
        $this->assertSame(20.00, $porForma['Cartão de Crédito']);
    }

    public function test_periodo_fora_do_intervalo_nao_conta(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->venderSplit($contexto);

        $resumo = app(ResumoDiaService::class)->porPeriodo(
            today()->subDays(10)->toDateString(),
            today()->subDays(1)->toDateString(),
        );

        $this->assertSame(0.0, $resumo['totalRecebido']);
        $this->assertCount(0, $resumo['linhas']);
    }

    public function test_estorno_neta_no_liquido_do_periodo(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->venderSplit($contexto);

        app(VendaService::class)->cancelarVendaProduto(VendaProduto::latest('id')->firstOrFail());

        $hoje = today()->toDateString();
        $resumo = app(ResumoDiaService::class)->porPeriodo($hoje, $hoje);

        $this->assertSame(100.00, $resumo['totalRecebido']);
        $this->assertSame(100.00, $resumo['totalEstornado']);
        $this->assertSame(0.0, $resumo['liquido']);
    }

    public function test_baixa_registra_conta_de_destino(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->venderSplit($contexto);

        $caixa = Conta::withoutGlobalScopes()->where('empresa_id', $contexto['empresa']->id)->where('eh_caixa_padrao', true)->firstOrFail();
        $banco = Conta::withoutGlobalScopes()->where('empresa_id', $contexto['empresa']->id)->where('eh_destino_recebivel_padrao', true)->firstOrFail();

        // Dinheiro cai na gaveta (Caixa); pix/cartão vão para a conta bancária (destino recebível).
        $this->assertSame($caixa->id, BaixaPagamento::where('forma_pagamento_nome', 'Dinheiro')->firstOrFail()->conta_id);
        $this->assertSame($banco->id, BaixaPagamento::where('forma_pagamento_nome', 'Pix')->firstOrFail()->conta_id);
        $this->assertSame($banco->id, BaixaPagamento::where('forma_pagamento_nome', 'Cartão de Crédito')->firstOrFail()->conta_id);
    }

    public function test_extrato_da_conta_bancaria_mostra_os_recebimentos(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->venderSplit($contexto);

        $banco = Conta::withoutGlobalScopes()->where('empresa_id', $contexto['empresa']->id)->where('eh_destino_recebivel_padrao', true)->firstOrFail();

        $resp = $this->get(route('contas.extrato', $banco));
        $resp->assertOk();
        $resp->assertSee('Recebimentos que caíram nesta conta');
        $resp->assertSee('Pix');
        $resp->assertSee('Cartão de Crédito');
    }

    public function test_tela_de_recebimentos_renderiza_com_as_formas(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->venderSplit($contexto);

        $resp = $this->get(route('caixas.recebimentos', [
            'de' => today()->toDateString(),
            'ate' => today()->toDateString(),
        ]));

        $resp->assertOk();
        $resp->assertSee('Recebimentos por forma');
        $resp->assertSee('Dinheiro');
        $resp->assertSee('Pix');
        $resp->assertSee('Cartão de Crédito');
    }
}
