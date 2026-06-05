<?php

namespace Tests\Feature\Venda;

use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Venda\Models\VendaEtapas;
use App\Modules\Venda\Models\VendaProduto;
use Database\Factories\CaixaFactory;
use Database\Factories\ClienteFactory;
use Database\Factories\ProdutoFactory;
use Database\Factories\ServicoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * Smoke do endpoint POST /vendas (VendaController::store) — o caminho que
 * dispara $servico->isEtapas() (corrigido de isPacote no refactor) e toda a
 * cascata Venda -> Pagamento -> Parcela. Cobre os 3 tipos de venda exercendo
 * o request real (CriarVendaRequest) e suas regras por tipo.
 *
 * Importante: tratarErro() converte exceptions genericas em redirect-back com
 * flash 'erro' (NAO 500). Por isso os asserts checam o redirect de SUCESSO
 * (vendas.index + flash 'sucesso') e a persistencia no banco — um residuo do
 * refactor (rota/relacao/coluna inexistente) cairia no redirect-back com 'erro'
 * e seria pego aqui.
 */
class VendaStoreSmokeTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    public function test_cria_venda_de_servico_unico_a_prazo(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $cliente = ClienteFactory::new()->create(['rede_id' => $rede->id]);
        $servico = ServicoFactory::new()->avulso()->create([
            'rede_id' => $rede->id,
            'valor' => 200.00,
        ]);

        $resp = $this->post(route('vendas.store'), [
            'tipo_venda' => 'servico',
            'cliente_id' => $cliente->id,
            'servico_id' => $servico->id,
            'atendente_id' => $contexto['usuario']->id,
            'data' => now()->addDay()->format('Y-m-d'),
            'horario' => '10:00',
            'condicao_pagamento' => 'a_prazo',
            'forma_pagamento' => 'pix',
            'forma_recebimento_prazo' => 'carne',
            'numero_parcelas' => 3,
            'primeiro_vencimento' => now()->addMonth()->format('Y-m-d'),
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertRedirect(route('vendas.index'));
        $resp->assertSessionHas('sucesso');
        $resp->assertSessionMissing('erro');

        $this->assertDatabaseHas('agendamentos', [
            'cliente_id' => $cliente->id,
            'servico_id' => $servico->id,
        ]);
        $this->assertSame(1, Agendamento::count());
    }

    public function test_cria_venda_de_servico_unico_a_vista_com_caixa_aberto(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empresa = $contexto['empresa'];

        CaixaFactory::new()->aberto()->create([
            'rede_id' => $rede->id,
            'empresa_id' => $empresa->id,
            'usuario_id' => $contexto['usuario']->id,
        ]);

        $cliente = ClienteFactory::new()->create(['rede_id' => $rede->id]);
        $servico = ServicoFactory::new()->avulso()->create([
            'rede_id' => $rede->id,
            'valor' => 150.00,
        ]);

        $resp = $this->post(route('vendas.store'), [
            'tipo_venda' => 'servico',
            'cliente_id' => $cliente->id,
            'servico_id' => $servico->id,
            'atendente_id' => $contexto['usuario']->id,
            'data' => now()->addDay()->format('Y-m-d'),
            'horario' => '14:30',
            'condicao_pagamento' => 'a_vista',
            'forma_pagamento' => 'dinheiro',
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertRedirect(route('vendas.index'));
        $resp->assertSessionHas('sucesso');
        $resp->assertSessionMissing('erro');

        $this->assertSame(1, Agendamento::count());
        $this->assertDatabaseHas('pagamentos', ['valor_total' => 150.00]);
    }

    /**
     * Regressao do 5o residuo do refactor pacote->etapas: VenderEtapasAction
     * criava VendaEtapas SEM a coluna `data` (DATE NOT NULL), quebrando toda
     * venda de servico em etapas (mascarado como redirect-back 'erro' pelo
     * tratarErro). Corrigido preenchendo `data` com a 1a sessao. Este teste
     * trava o caminho POST /vendas de etapas.
     */
    public function test_cria_venda_de_servico_em_etapas(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $cliente = ClienteFactory::new()->create(['rede_id' => $rede->id]);
        $servico = ServicoFactory::new()->etapas(3)->create([
            'rede_id' => $rede->id,
            'valor' => 100.00,
        ]);

        $resp = $this->post(route('vendas.store'), [
            'tipo_venda' => 'servico',
            'cliente_id' => $cliente->id,
            'servico_id' => $servico->id,
            'atendente_id' => $contexto['usuario']->id,
            'valor_total' => 300.00,
            'horario' => '09:00',
            'datas' => [
                now()->addDays(1)->format('Y-m-d'),
                now()->addDays(8)->format('Y-m-d'),
                now()->addDays(15)->format('Y-m-d'),
            ],
            'condicao_pagamento' => 'a_prazo',
            'forma_pagamento' => 'pix',
            'forma_recebimento_prazo' => 'carne',
            'numero_parcelas' => 3,
            'primeiro_vencimento' => now()->addMonth()->format('Y-m-d'),
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertRedirect(route('vendas.index'));
        $resp->assertSessionHas('sucesso');
        $resp->assertSessionMissing('erro');

        $this->assertSame(1, VendaEtapas::count());
        $etapas = VendaEtapas::firstOrFail();
        $this->assertSame($cliente->id, $etapas->cliente_id);
        $this->assertSame(3, $etapas->agendamentos()->count());
    }

    public function test_cria_venda_de_produto_a_prazo(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $cliente = ClienteFactory::new()->create(['rede_id' => $rede->id]);
        $produto = ProdutoFactory::new()->create([
            'rede_id' => $rede->id,
            'quantidade' => 20,
            'valor_venda' => 80.00,
        ]);

        $resp = $this->post(route('vendas.store'), [
            'tipo_venda' => 'produto',
            'cliente_id' => $cliente->id,
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => 2,
                'valor_unitario' => 80.00,
                'desconto' => 0,
                'acrescimo' => 0,
            ]],
            'condicao_pagamento' => 'a_prazo',
            'forma_pagamento' => 'pix',
            'forma_recebimento_prazo' => 'carne',
            'numero_parcelas' => 2,
            'primeiro_vencimento' => now()->addMonth()->format('Y-m-d'),
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertRedirect(route('vendas.index'));
        $resp->assertSessionHas('sucesso');
        $resp->assertSessionMissing('erro');

        $this->assertSame(1, VendaProduto::count());
        $this->assertDatabaseHas('pagamentos', ['valor_total' => 160.00]);
        $this->assertSame(18, $produto->fresh()->quantidade);
    }

    public function test_cria_venda_de_produto_a_vista_com_caixa_aberto(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empresa = $contexto['empresa'];

        CaixaFactory::new()->aberto()->create([
            'rede_id' => $rede->id,
            'empresa_id' => $empresa->id,
            'usuario_id' => $contexto['usuario']->id,
        ]);

        $produto = ProdutoFactory::new()->create([
            'rede_id' => $rede->id,
            'quantidade' => 5,
            'valor_venda' => 40.00,
        ]);

        $resp = $this->post(route('vendas.store'), [
            'tipo_venda' => 'produto',
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => 1,
                'valor_unitario' => 40.00,
                'desconto' => 0,
                'acrescimo' => 0,
            ]],
            'condicao_pagamento' => 'a_vista',
            'forma_pagamento' => 'dinheiro',
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertRedirect(route('vendas.index'));
        $resp->assertSessionHas('sucesso');
        $resp->assertSessionMissing('erro');

        $this->assertSame(1, VendaProduto::count());
        $this->assertSame(4, $produto->fresh()->quantidade);
    }
}
