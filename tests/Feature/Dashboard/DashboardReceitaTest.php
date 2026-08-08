<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Modules\Dashboard\Services\DashboardService;
use Database\Factories\{BaixaPagamentoFactory, PagamentoFactory, ParcelaPagamentoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O que o dashboard chama de "Receita do mes" tem de ser a mesma conta que o
 * caixa do dia e o extrato da conta fazem: o LIQUIDO efetivamente recebido.
 *
 * Dois erros moravam aqui e sao o alvo destes testes:
 *  1. baixa estornada contava como receita — o painel anunciava faturamento de
 *     uma venda cancelada enquanto o caixa do mesmo dia mostrava resultado zero;
 *  2. a soma pegava so `valor`, ignorando multa/juros/desconto, divergindo de
 *     `BaixaPagamento::valorTotal()`, usado em todo o resto do sistema.
 */
class DashboardReceitaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{rede_id: int, empresa_id: int} */
    private function baseDoTenant(array $contexto): array
    {
        return [
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
        ];
    }

    /** Cria uma baixa no mes corrente, pendurada num titulo/parcela do tenant. */
    private function baixaDoMes(array $contexto, array $atributos = []): void
    {
        $base = $this->baseDoTenant($contexto);

        $pagamento = PagamentoFactory::new()->create($base);
        $parcela = ParcelaPagamentoFactory::new()->create($base + ['pagamento_id' => $pagamento->id]);

        BaixaPagamentoFactory::new()->create($base + [
            'parcela_pagamento_id' => $parcela->id,
            'data' => now(),
        ] + $atributos);
    }

    public function test_baixa_estornada_nao_conta_como_receita(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $this->baixaDoMes($contexto, ['valor' => 30.00, 'estornado_em' => now()]);
        $this->baixaDoMes($contexto, ['valor' => 45.00, 'estornado_em' => now()]);

        $this->assertSame(
            0.0,
            app(DashboardService::class)->receitaMes(),
            'Venda cancelada nao e faturamento: o dashboard tem de bater com o caixa do dia.',
        );
    }

    public function test_receita_soma_apenas_as_baixas_vivas(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $this->baixaDoMes($contexto, ['valor' => 100.00]);
        $this->baixaDoMes($contexto, ['valor' => 40.00, 'estornado_em' => now()]);

        $this->assertSame(100.00, app(DashboardService::class)->receitaMes());
    }

    public function test_receita_usa_o_valor_liquido_e_nao_so_o_principal(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // 100 + 12 de multa + 8 de juros − 5 de desconto = 115 liquido, contra
        // 100 de principal: o numero so bate se a soma for a liquida.
        $this->baixaDoMes($contexto, [
            'valor' => 100.00,
            'multa' => 12.00,
            'juros' => 8.00,
            'desconto' => 5.00,
        ]);

        $this->assertSame(
            115.00,
            app(DashboardService::class)->receitaMes(),
            'Mesma conta de BaixaPagamento::valorTotal().',
        );
    }

    public function test_grafico_de_6_meses_segue_a_mesma_regra_dos_cards(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $this->baixaDoMes($contexto, ['valor' => 80.00]);
        $this->baixaDoMes($contexto, ['valor' => 25.00, 'estornado_em' => now()]);

        $meses = app(DashboardService::class)->fluxoUltimos6Meses();
        $mesAtual = end($meses);

        $this->assertSame(
            80.00,
            $mesAtual['receita'],
            'A curva nao pode contradizer o numero exibido logo acima dela.',
        );
    }

    public function test_receita_do_mes_isola_por_empresa(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $outra = $this->criarRede('outra');

        $this->baixaDoMes($contexto, ['valor' => 70.00]);
        $this->baixaDoMes($outra, ['valor' => 900.00]);

        $this->assertSame(
            70.00,
            app(DashboardService::class)->receitaMes(),
            'EmpresaTrait mantem a receita da empresa em contexto.',
        );
    }
}
