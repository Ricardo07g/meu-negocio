<?php

declare(strict_types=1);

namespace Tests\Feature\Caixa;

use App\Modules\Caixa\Models\BaixaDespesa;
use App\Modules\Caixa\Services\NavegacaoCaixaService;
use App\Modules\Tenant\Models\Empresa;
use Carbon\Carbon;
use Database\Factories\{BaixaPagamentoFactory, CaixaFactory, DespesaFactory, PagamentoFactory, ParcelaDespesaFactory, ParcelaPagamentoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setas do Caixa Diário: saltam para o dia com movimento mais próximo, não para
 * o dia vizinho.
 *
 * "Movimento" não é só caixa: venda no cartão/pix registra a baixa sem caixa
 * aberto (ADR-0011) e a tela mostra esse dia (ADR-0014). Por isso os testes
 * travam as quatro fontes — caixa, recebimento, estorno e despesa paga — e o
 * isolamento: a seta nunca para num dia que só tem movimento de outra unidade.
 */
class NavegacaoCaixaTest extends TestCase
{
    use RefreshDatabase;

    private const HOJE = '2026-09-24';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(self::HOJE.' 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function navegacao(): NavegacaoCaixaService
    {
        return app(NavegacaoCaixaService::class);
    }

    /** Autentica o admin com a unidade em contexto, como a tela do Caixa deixa a sessão. */
    private function entrar(array $contexto, array $empresas = []): void
    {
        $this->actingAs($contexto['usuario']);
        session([
            'empresas_atuais' => $empresas ?: [$contexto['empresa']->id],
            'empresa_contexto_atual' => $contexto['empresa']->id,
        ]);
    }

    private function caixa(array $contexto, string $dia, ?Empresa $empresa = null): void
    {
        $factory = CaixaFactory::new();
        ($dia < self::HOJE ? $factory->fechado() : $factory)->create([
            'empresa_id' => ($empresa ?? $contexto['empresa'])->id,
            'usuario_id' => $contexto['usuario']->id,
            'data' => $dia,
        ]);
    }

    /** Recebimento sem caixa — o caso da venda no cartão. */
    private function recebimento(array $contexto, string $dia, ?string $estornadoEm = null, ?Empresa $empresa = null): void
    {
        $pagamento = PagamentoFactory::new()->create(['empresa_id' => ($empresa ?? $contexto['empresa'])->id]);
        $parcela = ParcelaPagamentoFactory::new()->create(['pagamento_id' => $pagamento->id]);

        BaixaPagamentoFactory::new()->create([
            'parcela_pagamento_id' => $parcela->id,
            'forma_pagamento_nome' => 'Cartão de Crédito',
            'data' => $dia.' 15:00:00',
            'estornado_em' => $estornadoEm ? $estornadoEm.' 16:00:00' : null,
        ]);
    }

    private function despesaPaga(array $contexto, string $dia): void
    {
        $despesa = DespesaFactory::new()->create(['empresa_id' => $contexto['empresa']->id]);
        $parcela = ParcelaDespesaFactory::new()->create(['despesa_id' => $despesa->id]);

        BaixaDespesa::withoutGlobalScopes()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'parcela_despesa_id' => $parcela->id,
            'valor' => 80,
            'multa' => 0,
            'juros' => 0,
            'desconto' => 0,
            'forma_pagamento_nome' => 'Pix',
            'data' => $dia.' 10:00:00',
        ]);
    }

    public function test_seta_anterior_pula_dias_vazios_ate_o_ultimo_caixa(): void
    {
        $contexto = $this->criarRede();
        $this->caixa($contexto, '2026-09-02');
        $this->caixa($contexto, '2026-09-10'); // fechado
        $this->entrar($contexto);

        $this->assertSame('2026-09-10', $this->navegacao()->anterior(self::HOJE));
        $this->assertSame('2026-09-02', $this->navegacao()->anterior('2026-09-10'));
    }

    public function test_seta_anterior_para_em_dia_sem_caixa_com_venda_no_cartao(): void
    {
        $contexto = $this->criarRede();
        $this->caixa($contexto, '2026-09-10');
        $this->recebimento($contexto, '2026-09-15');
        $this->entrar($contexto);

        $this->assertSame(
            '2026-09-15',
            $this->navegacao()->anterior(self::HOJE),
            'Venda no cartão não exige caixa (ADR-0011): esse dia não pode sumir da navegação.'
        );
    }

    public function test_seta_para_em_dia_so_com_despesa_paga(): void
    {
        $contexto = $this->criarRede();
        $this->caixa($contexto, '2026-09-10');
        $this->despesaPaga($contexto, '2026-09-18');
        $this->entrar($contexto);

        $this->assertSame('2026-09-18', $this->navegacao()->anterior(self::HOJE));
        $this->assertSame('2026-09-18', $this->navegacao()->proximo('2026-09-10'));
    }

    public function test_seta_para_no_dia_do_estorno_nao_so_no_da_venda(): void
    {
        $contexto = $this->criarRede();
        $this->recebimento($contexto, '2026-09-05', estornadoEm: '2026-09-12');
        $this->entrar($contexto);

        $this->assertSame('2026-09-12', $this->navegacao()->anterior(self::HOJE));
        $this->assertSame('2026-09-05', $this->navegacao()->anterior('2026-09-12'));
    }

    public function test_seta_proxima_salta_ao_dia_seguinte_com_movimento(): void
    {
        $contexto = $this->criarRede();
        $this->caixa($contexto, '2026-09-02');
        $this->caixa($contexto, '2026-09-10');
        $this->entrar($contexto);

        $this->assertSame('2026-09-10', $this->navegacao()->proximo('2026-09-02'));
    }

    public function test_depois_do_ultimo_movimento_a_seta_proxima_cai_em_hoje(): void
    {
        $contexto = $this->criarRede();
        $this->caixa($contexto, '2026-09-10');
        $this->entrar($contexto);

        $this->assertSame(self::HOJE, $this->navegacao()->proximo('2026-09-10'));
    }

    public function test_em_hoje_a_seta_proxima_fica_desabilitada(): void
    {
        $contexto = $this->criarRede();
        $this->caixa($contexto, self::HOJE);
        $this->entrar($contexto);

        $this->assertNull($this->navegacao()->proximo(self::HOJE));
    }

    public function test_sem_movimento_anterior_a_seta_anterior_fica_desabilitada(): void
    {
        $contexto = $this->criarRede();
        $this->caixa($contexto, '2026-09-10');
        $this->entrar($contexto);

        $this->assertNull($this->navegacao()->anterior('2026-09-10'));
    }

    public function test_movimento_de_outra_unidade_nao_vira_parada_da_seta(): void
    {
        $contexto = $this->criarRede();
        $outraUnidade = $this->criarEmpresaExtra($contexto['rede']->id, 'Unidade B');

        $this->caixa($contexto, '2026-09-02');
        $this->caixa($contexto, '2026-09-15', $outraUnidade);
        $this->recebimento($contexto, '2026-09-20', empresa: $outraUnidade);

        $this->entrar($contexto, [$contexto['empresa']->id, $outraUnidade->id]);

        $this->assertSame('2026-09-02', $this->navegacao()->anterior(self::HOJE));
        $this->assertSame(self::HOJE, $this->navegacao()->proximo('2026-09-02'));
    }

    public function test_movimento_de_outra_rede_nao_vira_parada_da_seta(): void
    {
        $redeA = $this->criarRede('A');
        $redeB = $this->criarRede('B');

        $this->caixa($redeA, '2026-09-02');
        $this->caixa($redeB, '2026-09-15');
        $this->recebimento($redeB, '2026-09-20');

        $this->entrar($redeA);

        $this->assertSame('2026-09-02', $this->navegacao()->anterior(self::HOJE));
    }

    public function test_tela_leva_as_setas_para_os_dias_com_movimento(): void
    {
        $contexto = $this->criarRede();
        $this->caixa($contexto, '2026-09-02');
        $this->recebimento($contexto, '2026-09-10');
        $this->caixa($contexto, '2026-09-18');
        $this->entrar($contexto);

        $this->get(route('caixas.index', ['data' => '2026-09-10']))
            ->assertOk()
            ->assertSee(route('caixas.index', ['data' => '2026-09-02']), false)
            ->assertSee(route('caixas.index', ['data' => '2026-09-18']), false)
            ->assertDontSee(route('caixas.index', ['data' => '2026-09-09']), false)
            ->assertDontSee(route('caixas.index', ['data' => '2026-09-11']), false);
    }

    public function test_tela_desabilita_a_seta_quando_nao_ha_para_onde_ir(): void
    {
        $contexto = $this->criarRede();
        $this->caixa($contexto, self::HOJE);
        $this->entrar($contexto);

        $this->get(route('caixas.index'))
            ->assertOk()
            ->assertSee('Nenhum movimento anterior')
            ->assertSee('Nenhum movimento posterior');
    }
}
