<?php

declare(strict_types=1);

namespace Tests\Feature\Caixa;

use App\Enums\TipoLancamento;
use App\Modules\Caixa\Services\CaixaService;
use App\Modules\Conta\Models\{Conta, Lancamento};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Razao unificado (ADR-0010): o caixa diario e uma sessao da conta-caixa. Abrir
 * liga a sessao a conta eh_caixa_padrao; sangria/reforco viram lancamentos
 * (debito/credito) ligados a essa sessao, e o saldo da sessao sai do razao.
 */
class CaixaSessaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_abrir_liga_a_conta_caixa_e_sangria_reforco_viram_lancamentos(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $service = app(CaixaService::class);

        $caixa = $service->abrir(100.00, today()->toDateString());

        $contaCaixa = Conta::where('empresa_id', $contexto['empresa']->id)
            ->where('eh_caixa_padrao', true)
            ->firstOrFail();

        $this->assertSame($contaCaixa->id, $caixa->conta_id, 'A sessão de caixa aponta para a conta-caixa padrão.');

        $service->registrarReforco($caixa, 50.00, 'Troco inicial');
        $service->registrarSangria($caixa, 30.00, 'Retirada para o banco');

        $reforco = Lancamento::where('caixa_id', $caixa->id)->where('categoria', 'reforco')->firstOrFail();
        $this->assertSame(TipoLancamento::Credito, $reforco->tipo);
        $this->assertSame($contaCaixa->id, (int) $reforco->conta_id);

        $sangria = Lancamento::where('caixa_id', $caixa->id)->where('categoria', 'sangria')->firstOrFail();
        $this->assertSame(TipoLancamento::Debito, $sangria->tipo);

        // Saldo da sessão = abertura + reforço − sangria = 100 + 50 − 30 = 120.
        $this->assertSame(120.00, $caixa->fresh()->saldoCalculado());
    }

    public function test_tela_do_caixa_lista_os_lancamentos_do_dia(): void
    {
        $this->criarRedeAutenticada();
        $service = app(CaixaService::class);

        $caixa = $service->abrir(0.0, today()->toDateString());
        $service->registrarReforco($caixa, 50.00, 'Troco do dia');

        $this->get(route('caixas.index', ['data' => today()->toDateString()]))
            ->assertOk()
            ->assertViewIs('caixa::index')
            ->assertSee('Troco do dia')
            ->assertSee('Reforço');
    }
}
