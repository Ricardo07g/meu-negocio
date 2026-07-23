<?php

declare(strict_types=1);

namespace Tests\Feature\Venda;

use App\Enums\{StatusPagamento, StatusParcela, TipoFormaPagamento, TipoLancamento};
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Models\BaixaPagamento;
use App\Modules\Conta\Models\Lancamento;
use App\Modules\Conta\Services\ContaService;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\FormaPagamento\Services\FormaPagamentoService;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Tenant\Models\Empresa;
use App\Modules\Venda\Models\VendaProduto;
use App\Modules\Venda\Services\VendaService;
use Database\Factories\{CaixaFactory, ClienteFactory, ProdutoFactory, ServicoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * Split de recebimentos numa venda (parte pix, parte dinheiro, parte cartão):
 * 1 título + 1 parcela à vista + N baixas (uma por forma), somando o total.
 *
 * Exercita o endpoint POST /vendas (CriarVendaRequest + processarVenda +
 * VendaService::baixarAVistaSeAplicavel em loop). Foco no impacto no caixa:
 * cada baixa é roteada pela forma (dinheiro → gaveta/lançamento; pix/cartão →
 * só baixa), e a exigência de caixa é por-linha.
 */
class VendaSplitRecebimentosTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    private function formaId(TipoFormaPagamento $tipo): int
    {
        return FormaPagamento::ativos()->where('tipo', $tipo->value)->firstOrFail()->id;
    }

    public function test_tela_de_nova_venda_renderiza_o_card_de_recebimentos(): void
    {
        $this->criarRedeAutenticada();

        $resp = $this->get(route('vendas.create'));

        $resp->assertOk();
        $resp->assertSee('Recebimentos');
        $resp->assertSee('id="recebToolbar"', false);
        $resp->assertSee('id="recebimentosLista"', false);
        $resp->assertSee('Falta receber');
    }

    public function test_tela_embute_formas_de_todas_as_empresas_acessiveis(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // Segunda empresa da MESMA rede, acessível pelo usuário (multi-empresa).
        $empresaB = Empresa::create(['rede_id' => $contexto['rede']->id, 'nome' => 'Filial B']);
        app(ContaService::class)->semearPadrao($contexto['rede']->id, $empresaB->id);
        app(FormaPagamentoService::class)->semearPadrao($contexto['rede']->id, $empresaB->id);
        session(['empresas_atuais' => [$contexto['empresa']->id, $empresaB->id]]);

        $resp = $this->get(route('vendas.create'));
        $resp->assertOk();

        // As formas de AMBAS as empresas ficam embutidas (o JS filtra pela selecionada).
        $empresaIds = $resp->viewData('formas')->pluck('empresa_id')->unique()->all();
        $this->assertContains($contexto['empresa']->id, $empresaIds);
        $this->assertContains($empresaB->id, $empresaIds);
    }

    private function criarProduto(int $redeId, float $valor): int
    {
        return ProdutoFactory::new()->create([
            'rede_id' => $redeId,
            'quantidade' => 20,
            'valor_venda' => $valor,
        ])->id;
    }

    /** Payload base de venda de produto de 1 unidade a `$valor`. */
    private function payloadProduto(int $produtoId, float $valor, array $recebimentos): array
    {
        return [
            'tipo_venda' => 'produto',
            'itens' => [[
                'produto_id' => $produtoId,
                'quantidade' => 1,
                'valor_unitario' => $valor,
                'desconto' => 0,
                'acrescimo' => 0,
            ]],
            'recebimentos' => $recebimentos,
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ];
    }

    public function test_split_imediato_quita_venda_com_uma_baixa_por_forma(): void
    {
        $contexto = $this->criarRedeAutenticada();
        CaixaFactory::new()->aberto()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
        ]);

        $produtoId = $this->criarProduto($contexto['rede']->id, 100.00);

        $resp = $this->post(route('vendas.store'), $this->payloadProduto($produtoId, 100.00, [
            ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Dinheiro), 'valor' => 50.00],
            ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Pix), 'valor' => 30.00],
            ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::CartaoCredito), 'valor' => 20.00],
        ]));

        $resp->assertRedirect(route('vendas.index'));
        $resp->assertSessionHas('sucesso');
        $resp->assertSessionMissing('erro');

        $pagamento = Pagamento::with('parcelas')->latest('id')->firstOrFail();
        $this->assertSame(StatusPagamento::Pago, $pagamento->status);
        $this->assertCount(1, $pagamento->parcelas, 'Split à vista gera 1 parcela.');
        $this->assertSame(StatusParcela::Pago, $pagamento->parcelas->first()->status);
        $this->assertSame(100.00, (float) $pagamento->parcelas->first()->valor_pago);

        // Uma baixa por forma.
        $this->assertSame(3, BaixaPagamento::count(), 'Cada recebimento vira uma baixa.');

        // Só a linha de dinheiro (gaveta) gera lançamento no caixa; pix/cartão não.
        $this->assertSame(1, Lancamento::count(), 'Só dinheiro toca a gaveta.');
        $this->assertSame(50.00, (float) Lancamento::where('tipo', TipoLancamento::Credito)->sum('valor'));
    }

    public function test_split_com_dinheiro_exige_caixa_aberto_e_nao_persiste_sem_ele(): void
    {
        $contexto = $this->criarRedeAutenticada();
        // Sem caixa aberto de propósito.
        $produtoId = $this->criarProduto($contexto['rede']->id, 100.00);

        $resp = $this->post(route('vendas.store'), $this->payloadProduto($produtoId, 100.00, [
            ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Dinheiro), 'valor' => 50.00],
            ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Pix), 'valor' => 50.00],
        ]));

        $resp->assertRedirect();
        $resp->assertSessionHas('erro');
        $resp->assertSessionMissing('sucesso');

        $this->assertSame(0, Pagamento::count(), 'Nada é persistido sem caixa.');
        $this->assertSame(0, BaixaPagamento::count());
        $this->assertSame(0, VendaProduto::count());
    }

    public function test_split_so_cartao_e_pix_nao_exige_caixa(): void
    {
        $contexto = $this->criarRedeAutenticada();
        // Sem caixa aberto: cartão/pix não caem na gaveta.
        $produtoId = $this->criarProduto($contexto['rede']->id, 100.00);

        $resp = $this->post(route('vendas.store'), $this->payloadProduto($produtoId, 100.00, [
            ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::CartaoCredito), 'valor' => 50.00],
            ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Pix), 'valor' => 50.00],
        ]));

        $resp->assertRedirect(route('vendas.index'));
        $resp->assertSessionHas('sucesso');
        $resp->assertSessionMissing('erro');

        $this->assertSame(StatusPagamento::Pago, Pagamento::latest('id')->firstOrFail()->status);
        $this->assertSame(2, BaixaPagamento::count());
        $this->assertSame(0, Lancamento::count(), 'Cartão e Pix não geram lançamento na gaveta.');
    }

    public function test_soma_dos_recebimentos_diferente_do_total_e_rejeitada(): void
    {
        $contexto = $this->criarRedeAutenticada();
        CaixaFactory::new()->aberto()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
        ]);
        $produtoId = $this->criarProduto($contexto['rede']->id, 100.00);

        // Soma 80 != total 100.
        $resp = $this->post(route('vendas.store'), $this->payloadProduto($produtoId, 100.00, [
            ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Dinheiro), 'valor' => 50.00],
            ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Pix), 'valor' => 30.00],
        ]));

        $resp->assertSessionHasErrors('recebimentos');
        $this->assertSame(0, Pagamento::count(), 'Under-payment não persiste.');
        $this->assertSame(0, BaixaPagamento::count());
    }

    public function test_forma_de_outra_empresa_e_rejeitada(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $produtoId = $this->criarProduto($contexto['rede']->id, 100.00);

        // Segunda rede/empresa: sua forma NÃO é acessível pela rede autenticada.
        $outra = $this->criarRede('outra');
        $formaOutraEmpresa = $this->formaPagamento($outra['rede'], TipoFormaPagamento::Dinheiro)->id;

        $resp = $this->post(route('vendas.store'), $this->payloadProduto($produtoId, 100.00, [
            ['forma_pagamento_id' => $formaOutraEmpresa, 'valor' => 100.00],
        ]));

        $resp->assertSessionHasErrors('recebimentos.0.forma_pagamento_id');
        $this->assertSame(0, Pagamento::count(), 'Forma de outra empresa não persiste venda.');
    }

    public function test_crediario_single_line_gera_parcelas_do_cliente_sem_baixa(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $cliente = ClienteFactory::new()->create(['rede_id' => $contexto['rede']->id]);
        $produtoId = $this->criarProduto($contexto['rede']->id, 300.00);

        $resp = $this->post(route('vendas.store'), array_merge(
            $this->payloadProduto($produtoId, 300.00, [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Crediario), 'valor' => 300.00],
            ]),
            [
                'cliente_id' => $cliente->id,
                'numero_parcelas' => 3,
                'primeiro_vencimento' => now()->addMonth()->format('Y-m-d'),
                'forma_recebimento_prazo' => 'carne',
            ],
        ));

        $resp->assertRedirect(route('vendas.index'));
        $resp->assertSessionHas('sucesso');
        $resp->assertSessionMissing('erro');

        $pagamento = Pagamento::with('parcelas')->latest('id')->firstOrFail();
        $this->assertSame(StatusPagamento::Pendente, $pagamento->status, 'Crediário vira título a receber.');
        $this->assertCount(3, $pagamento->parcelas);
        $this->assertSame(0, BaixaPagamento::count(), 'Crediário não baixa na criação.');
    }

    public function test_crediario_combinado_com_forma_imediata_e_rejeitado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        CaixaFactory::new()->aberto()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
        ]);
        $cliente = ClienteFactory::new()->create(['rede_id' => $contexto['rede']->id]);
        $produtoId = $this->criarProduto($contexto['rede']->id, 300.00);

        $resp = $this->post(route('vendas.store'), array_merge(
            $this->payloadProduto($produtoId, 300.00, [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Crediario), 'valor' => 200.00],
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Dinheiro), 'valor' => 100.00],
            ]),
            [
                'cliente_id' => $cliente->id,
                'numero_parcelas' => 3,
                'primeiro_vencimento' => now()->addMonth()->format('Y-m-d'),
                'forma_recebimento_prazo' => 'carne',
            ],
        ));

        $resp->assertSessionHasErrors('recebimentos');
        $this->assertSame(0, Pagamento::count(), 'Mix crediário + imediato não persiste (v1).');
    }

    public function test_estorno_de_venda_com_split_reverte_so_a_gaveta(): void
    {
        $contexto = $this->criarRedeAutenticada();
        CaixaFactory::new()->aberto()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
        ]);
        $produtoId = $this->criarProduto($contexto['rede']->id, 100.00);

        $this->post(route('vendas.store'), $this->payloadProduto($produtoId, 100.00, [
            ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Dinheiro), 'valor' => 50.00],
            ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::CartaoCredito), 'valor' => 50.00],
        ]))->assertSessionHas('sucesso');

        $venda = VendaProduto::latest('id')->firstOrFail();

        app(VendaService::class)->cancelarVendaProduto($venda);

        // Todas as baixas marcadas como estornadas.
        $this->assertSame(0, BaixaPagamento::whereNull('estornado_em')->count());
        $this->assertSame(2, BaixaPagamento::whereNotNull('estornado_em')->count());

        // Só a baixa de dinheiro (gaveta) tem lançamento a reverter (contra-lançamento de estorno).
        $this->assertSame(1, Lancamento::where('categoria', 'estorno')->count());
        $this->assertSame(StatusPagamento::Estornado, $venda->pagamento->fresh()->status);
    }

    public function test_detalhe_da_venda_mostra_todas_as_formas_do_split(): void
    {
        $contexto = $this->criarRedeAutenticada();
        CaixaFactory::new()->aberto()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
        ]);
        $produtoId = $this->criarProduto($contexto['rede']->id, 100.00);

        $this->post(route('vendas.store'), $this->payloadProduto($produtoId, 100.00, [
            ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Dinheiro), 'valor' => 50.00],
            ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Pix), 'valor' => 30.00],
            ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::CartaoCredito), 'valor' => 20.00],
        ]))->assertSessionHas('sucesso');

        $venda = VendaProduto::latest('id')->firstOrFail();

        // Antes do fix, o detalhe mostrava só "Cartão de Crédito" (forma da última baixa).
        $resp = $this->get(route('vendas.show', ['tipo' => 'produto', 'id' => $venda->id]));
        $resp->assertOk();
        $resp->assertSee('Dinheiro');
        $resp->assertSee('Pix');
        $resp->assertSee('Cartão de Crédito');
    }

    public function test_split_em_servico_unico(): void
    {
        $contexto = $this->criarRedeAutenticada();
        CaixaFactory::new()->aberto()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
        ]);

        $cliente = ClienteFactory::new()->create(['rede_id' => $contexto['rede']->id]);
        $servico = ServicoFactory::new()->avulso()->create([
            'rede_id' => $contexto['rede']->id,
            'valor' => 150.00,
        ]);

        $resp = $this->post(route('vendas.store'), [
            'tipo_venda' => 'servico',
            'cliente_id' => $cliente->id,
            'servico_id' => $servico->id,
            'atendente_id' => $contexto['usuario']->id,
            'data' => now()->addDay()->format('Y-m-d'),
            'horario' => '10:00',
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Dinheiro), 'valor' => 100.00],
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Pix), 'valor' => 50.00],
            ],
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertRedirect(route('vendas.index'));
        $resp->assertSessionHas('sucesso');
        $resp->assertSessionMissing('erro');

        $this->assertSame(1, Agendamento::count());
        $this->assertSame(StatusPagamento::Pago, Pagamento::latest('id')->firstOrFail()->status);
        $this->assertSame(2, BaixaPagamento::count());
        // Só dinheiro cai na gaveta.
        $this->assertSame(100.00, (float) Lancamento::where('tipo', TipoLancamento::Credito)->sum('valor'));
    }
}
