<?php

namespace Tests\Feature\Dashboard;

use App\Enums\StatusAgendamento;
use App\Enums\StatusParcela;
use App\Modules\Dashboard\Services\DashboardService;
use Database\Factories\AgendamentoFactory;
use Database\Factories\ClienteFactory;
use Database\Factories\PagamentoFactory;
use Database\Factories\ParcelaPagamentoFactory;
use Database\Factories\ServicoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre o modulo Dashboard:
 *  - smoke: GET dashboard autenticado retorna 200 e renderiza a view
 *  - agregacoes do DashboardService com dados semeados
 *  - escopo multi-tenant: rede A nao soma dados da rede B
 *
 * As agregacoes que dependem de BaixaPagamento/BaixaDespesa (receitaMes,
 * fluxoUltimos6Meses) ficam de fora dos asserts numericos porque nao ha
 * factory de baixa no projeto; o smoke garante que essas chamadas nao
 * quebram a renderizacao.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_autenticado_retorna_200(): void
    {
        $this->criarRedeAutenticada();

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard::dashboard');
        $response->assertViewHas('agendamentosHoje');
        $response->assertViewHas('totalClientes');
        $response->assertViewHas('proximosAgendamentos');
        $response->assertViewHas('parcelasVencendo');
    }

    public function test_dashboard_exige_autenticacao(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_agendamentos_hoje_conta_apenas_do_dia(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // Dois hoje, um amanha (nao deve contar).
        AgendamentoFactory::new()->count(2)->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'inicio' => today()->copy()->setTime(10, 0),
            'fim' => today()->copy()->setTime(11, 0),
        ]);
        AgendamentoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'inicio' => today()->copy()->addDay()->setTime(10, 0),
            'fim' => today()->copy()->addDay()->setTime(11, 0),
        ]);

        $this->assertSame(2, app(DashboardService::class)->agendamentosHoje());
    }

    public function test_total_clientes_reflete_cadastro_da_rede(): void
    {
        $contexto = $this->criarRedeAutenticada();

        ClienteFactory::new()->count(3)->create(['rede_id' => $contexto['rede']->id]);

        $this->assertSame(3, app(DashboardService::class)->totalClientes());
    }

    public function test_contas_a_receber_soma_apenas_parcelas_pendentes(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $pagamento = PagamentoFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
        ]);

        // Duas pendentes (devem somar), uma paga (nao soma).
        ParcelaPagamentoFactory::new()->pendente()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'pagamento_id' => $pagamento->id,
            'valor' => 100.00,
            'valor_pago' => 0,
        ]);
        ParcelaPagamentoFactory::new()->pendente()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'pagamento_id' => $pagamento->id,
            'valor' => 60.00,
            'valor_pago' => 10.00, // saldo de 50
        ]);
        ParcelaPagamentoFactory::new()->paga()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'pagamento_id' => $pagamento->id,
            'valor' => 200.00,
            'valor_pago' => 200.00,
        ]);

        $service = app(DashboardService::class);

        $this->assertSame(2, $service->contasReceberQuantidade());
        // Saldo: (100 - 0) + (60 - 10) = 150
        $this->assertSame(150.0, $service->contasReceberTotal());
    }

    public function test_proximos_agendamentos_traz_apenas_futuros_do_dia_em_andamento(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // Futuro hoje, em andamento -> deve aparecer.
        $futuro = AgendamentoFactory::new()->confirmado()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'inicio' => now()->copy()->addHours(1),
            'fim' => now()->copy()->addHours(2),
        ]);

        // Cancelado -> fora.
        AgendamentoFactory::new()->cancelado()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'inicio' => now()->copy()->addHours(3),
            'fim' => now()->copy()->addHours(4),
        ]);

        // Amanha -> fora (so o dia corrente).
        AgendamentoFactory::new()->confirmado()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'inicio' => now()->copy()->addDay(),
            'fim' => now()->copy()->addDay()->addHour(),
        ]);

        $proximos = app(DashboardService::class)->proximosAgendamentos();

        $this->assertCount(1, $proximos);
        $this->assertSame($futuro->id, $proximos->first()->id);
    }

    public function test_parcelas_vencendo_traz_apenas_pendentes_no_intervalo(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $pagamento = PagamentoFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
        ]);

        // Vence em 3 dias, pendente -> aparece.
        $dentro = ParcelaPagamentoFactory::new()->pendente()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'pagamento_id' => $pagamento->id,
            'data_vencimento' => today()->copy()->addDays(3)->format('Y-m-d'),
            'status' => StatusParcela::Pendente,
        ]);

        // Vence em 30 dias (fora da janela de 7 dias) -> nao aparece.
        ParcelaPagamentoFactory::new()->pendente()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'pagamento_id' => $pagamento->id,
            'data_vencimento' => today()->copy()->addDays(30)->format('Y-m-d'),
            'status' => StatusParcela::Pendente,
        ]);

        // Vence em 2 dias mas ja paga -> nao aparece.
        ParcelaPagamentoFactory::new()->paga()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'pagamento_id' => $pagamento->id,
            'data_vencimento' => today()->copy()->addDays(2)->format('Y-m-d'),
        ]);

        $parcelas = app(DashboardService::class)->parcelasVencendo();

        $this->assertCount(1, $parcelas);
        $this->assertSame($dentro->id, $parcelas->first()->id);
    }

    public function test_agendamentos_por_status_do_mes_agrega_corretamente(): void
    {
        $contexto = $this->criarRedeAutenticada();

        AgendamentoFactory::new()->confirmado()->count(2)->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'inicio' => now()->copy()->startOfMonth()->addDays(2)->setTime(9, 0),
            'fim' => now()->copy()->startOfMonth()->addDays(2)->setTime(10, 0),
        ]);
        AgendamentoFactory::new()->cancelado()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'inicio' => now()->copy()->startOfMonth()->addDays(3)->setTime(9, 0),
            'fim' => now()->copy()->startOfMonth()->addDays(3)->setTime(10, 0),
        ]);

        $distribuicao = collect(app(DashboardService::class)->agendamentosPorStatusMes())
            ->keyBy('status');

        $this->assertSame(2, $distribuicao[StatusAgendamento::Confirmado->value]['total']);
        $this->assertSame(1, $distribuicao[StatusAgendamento::Cancelado->value]['total']);
        $this->assertSame(0, $distribuicao[StatusAgendamento::Finalizado->value]['total']);
    }

    public function test_dashboard_respeita_escopo_de_rede(): void
    {
        $redeA = $this->criarRede('A');
        $redeB = $this->criarRede('B');

        // Rede A: 2 clientes + 1 agendamento hoje (reaproveita um cliente
        // existente para nao inflar a contagem via factory aninhada).
        $clientesA = ClienteFactory::new()->count(2)->create(['rede_id' => $redeA['rede']->id]);
        $servicoA = ServicoFactory::new()->avulso()->create(['rede_id' => $redeA['rede']->id]);
        AgendamentoFactory::new()->create([
            'rede_id' => $redeA['rede']->id,
            'empresa_id' => $redeA['empresa']->id,
            'cliente_id' => $clientesA->first()->id,
            'servico_id' => $servicoA->id,
            'inicio' => today()->copy()->setTime(10, 0),
            'fim' => today()->copy()->setTime(11, 0),
        ]);

        // Rede B: 5 clientes + 4 agendamentos hoje (nao devem vazar para A).
        $clientesB = ClienteFactory::new()->count(5)->create(['rede_id' => $redeB['rede']->id]);
        $servicoB = ServicoFactory::new()->avulso()->create(['rede_id' => $redeB['rede']->id]);
        AgendamentoFactory::new()->count(4)->create([
            'rede_id' => $redeB['rede']->id,
            'empresa_id' => $redeB['empresa']->id,
            'cliente_id' => $clientesB->first()->id,
            'servico_id' => $servicoB->id,
            'inicio' => today()->copy()->setTime(12, 0),
            'fim' => today()->copy()->setTime(13, 0),
        ]);

        // Logado na rede A: ve apenas os proprios numeros.
        $this->actingAs($redeA['usuario']);
        session(['empresas_atuais' => [$redeA['empresa']->id]]);

        $service = app(DashboardService::class);
        $this->assertSame(2, $service->totalClientes());
        $this->assertSame(1, $service->agendamentosHoje());

        // Logado na rede B: ve apenas os proprios numeros.
        $this->actingAs($redeB['usuario']);
        session(['empresas_atuais' => [$redeB['empresa']->id]]);

        $service = app(DashboardService::class);
        $this->assertSame(5, $service->totalClientes());
        $this->assertSame(4, $service->agendamentosHoje());
    }
}
