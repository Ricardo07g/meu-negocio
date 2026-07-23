<?php

declare(strict_types=1);

namespace Tests\Feature\Venda;

use App\Enums\{StatusPagamento, TipoFormaPagamento};
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Models\Recebivel;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Tenant\Models\Empresa;
use App\Modules\Venda\Models\{VendaEtapas, VendaProduto};
use Database\Factories\{AgendamentoFactory, CaixaFactory, ClienteFactory, ProdutoFactory, ServicoFactory};
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

    /** Id da forma de pagamento (por tipo) da rede autenticada. */
    private function formaId(TipoFormaPagamento $tipo): int
    {
        return FormaPagamento::ativos()->where('tipo', $tipo->value)->firstOrFail()->id;
    }

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
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Pix), 'valor' => 200.00],
            ],
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
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Dinheiro), 'valor' => 150.00],
            ],
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
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Pix), 'valor' => 300.00],
            ],
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
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Pix), 'valor' => 160.00],
            ],
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

    /**
     * Regressao: um formulario real envia todos os campos como STRING
     * ("cliente_id=422"), enquanto os testes acima passavam int nativo e por
     * isso nao pegavam o bug. VendaController::store repassava $request->cliente_id
     * cru a VendaService::criarVendaProduto(?int $cliente_id) e, sob
     * declare(strict_types=1), PHP recusava a string -> TypeError (mascarado
     * como redirect-back 'erro' pelo tratarErro). Corrigido castando para ?int
     * no controller, preservando null na venda balcao.
     */
    public function test_cria_venda_de_produto_com_cliente_id_string_do_formulario(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $cliente = ClienteFactory::new()->create(['rede_id' => $rede->id]);
        $produto = ProdutoFactory::new()->create([
            'rede_id' => $rede->id,
            'quantidade' => 10,
            'valor_venda' => 50.00,
        ]);

        $resp = $this->post(route('vendas.store'), [
            'tipo_venda' => 'produto',
            'cliente_id' => (string) $cliente->id,
            'itens' => [[
                'produto_id' => (string) $produto->id,
                'quantidade' => '2',
                'valor_unitario' => '50.00',
                'desconto' => '0',
                'acrescimo' => '0',
            ]],
            'recebimentos' => [
                ['forma_pagamento_id' => (string) $this->formaId(TipoFormaPagamento::Pix), 'valor' => '100.00'],
            ],
            'forma_recebimento_prazo' => 'carne',
            'numero_parcelas' => '2',
            'primeiro_vencimento' => now()->addMonth()->format('Y-m-d'),
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertRedirect(route('vendas.index'));
        $resp->assertSessionHas('sucesso');
        $resp->assertSessionMissing('erro');

        $this->assertSame(1, VendaProduto::count());
        $this->assertSame($cliente->id, VendaProduto::firstOrFail()->cliente_id);
        $this->assertDatabaseHas('pagamentos', ['valor_total' => 100.00]);
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
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Dinheiro), 'valor' => 40.00],
            ],
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertRedirect(route('vendas.index'));
        $resp->assertSessionHas('sucesso');
        $resp->assertSessionMissing('erro');

        $this->assertSame(1, VendaProduto::count());
        $this->assertSame(4, $produto->fresh()->quantidade);
    }

    /**
     * Conflito de agenda ao vender servico em etapas: quando o profissional ja
     * possui agendamento em uma das datas, VenderEtapasAction lanca
     * ConflitoAgendamentoException com uma mensagem que NOMEIA o profissional e
     * lista o dia/horario ocupado, revertendo tudo (rollback). Regressao da
     * mensagem generica antiga ("Conflito de horario nas datas: ...").
     */
    public function test_conflito_de_etapas_informa_profissional_e_datas(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $profissional = $contexto['usuario'];

        $cliente = ClienteFactory::new()->create(['rede_id' => $rede->id]);
        $servico = ServicoFactory::new()->etapas(2)->create([
            'rede_id' => $rede->id,
            'valor' => 200.00,
        ]);

        // Agendamento ja existente do profissional na 1a data/horario da venda.
        $inicioOcupado = now()->addDays(1)->setTime(9, 0, 0);
        AgendamentoFactory::new()->create([
            'rede_id' => $rede->id,
            'empresa_id' => $contexto['empresa']->id,
            'cliente_id' => $cliente->id,
            'servico_id' => $servico->id,
            'atendente_id' => $profissional->id,
            'inicio' => $inicioOcupado,
            'fim' => $inicioOcupado->copy()->addMinutes(60),
        ]);

        $resp = $this->post(route('vendas.store'), [
            'tipo_venda' => 'servico',
            'cliente_id' => $cliente->id,
            'servico_id' => $servico->id,
            'atendente_id' => $profissional->id,
            'valor_total' => 200.00,
            'horario' => '09:00',
            'datas' => [
                $inicioOcupado->format('Y-m-d'),
                now()->addDays(8)->format('Y-m-d'),
            ],
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Pix), 'valor' => 200.00],
            ],
            'forma_recebimento_prazo' => 'carne',
            'numero_parcelas' => 2,
            'primeiro_vencimento' => now()->addMonth()->format('Y-m-d'),
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertSessionHas('erro');
        $resp->assertSessionMissing('sucesso');

        $erro = session('erro');
        $this->assertStringContainsString($profissional->nome, $erro);
        $this->assertStringContainsString($inicioOcupado->format('d/m/Y'), $erro);
        $this->assertStringContainsString('ocupada', $erro);

        // Rollback total: nenhuma VendaEtapas e apenas o agendamento pre-existente.
        $this->assertSame(0, VendaEtapas::count());
        $this->assertSame(1, Agendamento::count());
    }

    /**
     * Regressao: usuario com MAIS DE UMA empresa acessivel e SEM contexto
     * explicito selecionado gerava agendamento sem empresa_id (viola NOT NULL,
     * "Field 'empresa_id' doesn't have a default value") ao registrar a venda.
     * VendaController::store agora faz o fallback para a empresa padrao do
     * usuario, entao a venda cai numa empresa valida em vez de quebrar.
     */
    public function test_venda_sem_contexto_de_empresa_usa_empresa_padrao_do_usuario(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empresaPadrao = $contexto['empresa'];

        // Rede multi-empresa; sessao com todas e SEM empresa_contexto_atual (cenario do bug).
        $empresa2 = Empresa::create(['rede_id' => $rede->id, 'nome' => 'Filial Norte']);
        $empresa3 = Empresa::create(['rede_id' => $rede->id, 'nome' => 'Filial Sul']);
        session(['empresas_atuais' => [$empresaPadrao->id, $empresa2->id, $empresa3->id]]);
        session()->forget('empresa_contexto_atual');

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
            'horario' => '10:00',
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Pix), 'valor' => 150.00],
            ],
            'forma_recebimento_prazo' => 'carne',
            'numero_parcelas' => 2,
            'primeiro_vencimento' => now()->addMonth()->format('Y-m-d'),
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertRedirect(route('vendas.index'));
        $resp->assertSessionHas('sucesso');
        $resp->assertSessionMissing('erro');

        // A venda cai na empresa padrao do usuario (fallback), sem quebrar.
        $this->assertDatabaseHas('agendamentos', [
            'servico_id' => $servico->id,
            'empresa_id' => $empresaPadrao->id,
        ]);
    }

    /**
     * Contrato de validacao do servidor que a validacao via JS espelha: uma venda
     * de servico sem os campos obrigatorios volta com erros por campo (o layout
     * os exibe num SweetAlert), sem persistir nada.
     */
    public function test_venda_servico_sem_campos_obrigatorios_retorna_erros_de_validacao(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $servico = ServicoFactory::new()->avulso()->create([
            'rede_id' => $contexto['rede']->id,
            'valor' => 100.00,
        ]);

        // Servico unico sem cliente, atendente, data, horario nem recebimentos.
        $resp = $this->post(route('vendas.store'), [
            'tipo_venda' => 'servico',
            'servico_id' => $servico->id,
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertSessionHasErrors(['cliente_id', 'atendente_id', 'data', 'horario', 'recebimentos']);
        $this->assertSame(0, Agendamento::count());
    }

    /**
     * Crediário é forma de 1ª classe: escolhê-la FORÇA "a prazo" (a loja financia
     * o cliente em N parcelas), mesmo que o form envie "à vista". Gera título
     * Pendente com N parcelas do cliente e NÃO cria recebível de banco.
     */
    public function test_crediario_forca_a_prazo_e_gera_parcelas_do_cliente(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $cliente = ClienteFactory::new()->create(['rede_id' => $rede->id]);
        $produto = ProdutoFactory::new()->create([
            'rede_id' => $rede->id,
            'quantidade' => 10,
            'valor_venda' => 300.00,
        ]);

        // Sem toggle à-vista/a-prazo: escolher a forma Crediário já deriva "a prazo" (carnê).
        $resp = $this->post(route('vendas.store'), [
            'tipo_venda' => 'produto',
            'cliente_id' => $cliente->id,
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => 1,
                'valor_unitario' => 300.00,
                'desconto' => 0,
                'acrescimo' => 0,
            ]],
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Crediario), 'valor' => 300.00],
            ],
            'forma_recebimento_prazo' => 'carne',
            'numero_parcelas' => 3,
            'primeiro_vencimento' => now()->addMonth()->format('Y-m-d'),
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertRedirect(route('vendas.index'));
        $resp->assertSessionHas('sucesso');
        $resp->assertSessionMissing('erro');

        $pagamento = Pagamento::with('parcelas')->latest('id')->firstOrFail();
        $this->assertSame(StatusPagamento::Pendente, $pagamento->status, 'Crediário vira título Pendente (a receber do cliente).');
        $this->assertCount(3, $pagamento->parcelas, 'Deveria gerar 3 parcelas do cliente.');
        $this->assertSame(0, Recebivel::count(), 'Crediário NÃO gera recebível de banco.');
    }
}
