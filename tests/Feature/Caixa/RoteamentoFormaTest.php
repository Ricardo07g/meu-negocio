<?php

declare(strict_types=1);

namespace Tests\Feature\Caixa;

use App\Enums\{StatusCaixa, StatusParcela, TipoConta, TipoFormaPagamento, TipoLancamento};
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Caixa\Services\CaixaService;
use App\Modules\Conta\Models\Lancamento;
use App\Modules\Pagamento\Models\ParcelaPagamento;
use Database\Factories\{FormaPagamentoFactory, PagamentoFactory, ParcelaPagamentoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regime "fluxo, nao saldo" (ADR-0011): so o dinheiro (gaveta) gera Lancamento e
 * exige caixa aberto. Cartao, PIX (direto ou maquineta), boleto e crediario
 * registram SO a BaixaPagamento (o recebido por forma do dia) — sem lancamento,
 * sem recebivel, sem exigir caixa. Nao mantemos saldo de banco.
 */
class RoteamentoFormaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{contexto: array, parcela: ParcelaPagamento} */
    private function cenario(float $valor = 100.00): array
    {
        $contexto = $this->criarRedeAutenticada();

        $pagamento = PagamentoFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'valor_total' => $valor * 2,
        ]);

        $parcela = ParcelaPagamentoFactory::new()->pendente()->create([
            'pagamento_id' => $pagamento->id,
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'numero' => 1,
            'total' => 2,
            'valor' => $valor,
        ]);

        return compact('contexto', 'parcela');
    }

    private function abrirCaixa(array $contexto): void
    {
        Caixa::create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
            'data' => today()->toDateString(),
            'saldo_abertura' => 0,
            'status' => StatusCaixa::Aberto,
        ]);
    }

    public function test_dinheiro_cai_na_gaveta_com_caixa_aberto(): void
    {
        $c = $this->cenario();
        $this->abrirCaixa($c['contexto']);

        $baixa = app(CaixaService::class)->darBaixaParcelaPagamento(
            $c['parcela'], 100.00, $this->formaPagamento($c['contexto']['rede'], TipoFormaPagamento::Dinheiro)
        );

        $lancamento = Lancamento::where('baixa_pagamento_id', $baixa->id)->firstOrFail();
        $this->assertSame(TipoLancamento::Credito, $lancamento->tipo);
        $this->assertSame(TipoConta::Caixa, $lancamento->conta->tipo, 'Dinheiro cai na conta caixa.');
        $this->assertNotNull($lancamento->caixa_id, 'O lançamento da gaveta carrega a sessão de caixa.');
    }

    public function test_pix_direto_registra_so_a_baixa_sem_lancamento_e_sem_caixa(): void
    {
        $c = $this->cenario();

        // Sem caixa aberto de propósito: PIX direto nao exige caixa nem toca a gaveta.
        $baixa = app(CaixaService::class)->darBaixaParcelaPagamento(
            $c['parcela'], 100.00, $this->formaPagamento($c['contexto']['rede'], TipoFormaPagamento::Pix)
        );

        $this->assertSame(0, Lancamento::count(), 'PIX direto nao gera lançamento (fluxo, nao saldo).');
        $this->assertNull($baixa->caixa_id, 'PIX direto nao passa pela gaveta.');
        $this->assertSame(StatusParcela::Pago, $c['parcela']->fresh()->status, 'A parcela foi quitada.');
    }

    public function test_cartao_credito_registra_so_a_baixa(): void
    {
        $c = $this->cenario();

        $baixa = app(CaixaService::class)->darBaixaParcelaPagamento(
            $c['parcela'], 100.00, $this->formaPagamento($c['contexto']['rede'], TipoFormaPagamento::CartaoCredito),
            parcelasCartao: 3,
        );

        $this->assertSame(0, Lancamento::count(), 'Cartão nao gera lançamento imediato.');
        $this->assertNotNull($baixa->id, 'O recebimento no cartão vira uma baixa (recebido por forma).');
        $this->assertSame(StatusParcela::Pago, $c['parcela']->fresh()->status);
    }

    public function test_pix_maquineta_registra_so_a_baixa(): void
    {
        $c = $this->cenario(200.00);

        $forma = FormaPagamentoFactory::new()->pixMaquineta()->create([
            'rede_id' => $c['contexto']['rede']->id,
            'empresa_id' => $c['contexto']['empresa']->id,
        ]);

        // Sem caixa aberto: PIX-maquineta nao depende de caixa.
        $baixa = app(CaixaService::class)->darBaixaParcelaPagamento($c['parcela'], 200.00, $forma);

        $this->assertSame(0, Lancamento::count(), 'PIX-maquineta nao gera lançamento imediato.');
        $this->assertSame($forma->nome, $baixa->forma_pagamento_nome, 'A baixa guarda a forma para o painel do dia.');
        $this->assertSame(StatusParcela::Pago, $c['parcela']->fresh()->status);
    }
}
