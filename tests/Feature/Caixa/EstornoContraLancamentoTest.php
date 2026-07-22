<?php

declare(strict_types=1);

namespace Tests\Feature\Caixa;

use App\Enums\{StatusCaixa, StatusPagamento, TipoConta, TipoFormaPagamento, TipoLancamento};
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Caixa\Services\CaixaService;
use App\Modules\Conta\Models\Lancamento;
use App\Modules\Pagamento\Models\ParcelaPagamento;
use Database\Factories\{PagamentoFactory, ParcelaPagamentoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estorno no regime "fluxo, nao saldo" (ADR-0011): toda baixa estornada e marcada
 * (`estornado_em`) — e o marcador unico que o painel do dia neta. So a baixa da
 * gaveta (dinheiro) tem Lancamento a reverter (contra-lancamento de debito);
 * cartao/PIX/banco nao tem lancamento — nada a reverter, so a marca.
 */
class EstornoContraLancamentoTest extends TestCase
{
    use RefreshDatabase;

    private function parcelaPendente(array $contexto, float $valor): ParcelaPagamento
    {
        $pagamento = PagamentoFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'valor_total' => $valor * 2,
        ]);

        return ParcelaPagamentoFactory::new()->pendente()->create([
            'pagamento_id' => $pagamento->id,
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'numero' => 1,
            'total' => 2,
            'valor' => $valor,
        ]);
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

    public function test_estorno_de_dinheiro_gera_contra_lancamento_na_gaveta(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->abrirCaixa($contexto);
        $parcela = $this->parcelaPendente($contexto, 100.00);

        $baixa = app(CaixaService::class)->darBaixaParcelaPagamento(
            $parcela, 100.00, $this->formaPagamento($contexto['rede'], TipoFormaPagamento::Dinheiro)
        );

        app(CaixaService::class)->estornarPagamento($parcela->pagamento->fresh());

        // A baixa foi marcada como estornada (marcador do fluxo).
        $this->assertNotNull($baixa->fresh()->estornado_em, 'A baixa da gaveta e marcada como estornada.');

        // O credito do recebimento e o debito do estorno se anulam na conta caixa.
        $debito = Lancamento::where('baixa_pagamento_id', $baixa->id)
            ->where('tipo', TipoLancamento::Debito)
            ->firstOrFail();

        $this->assertSame('estorno', $debito->categoria);
        $this->assertSame(100.00, (float) $debito->valor, 'O estorno reverte o valor recebido.');
        $this->assertSame(TipoConta::Caixa, $debito->conta->tipo);
        $this->assertSame(0.0, $debito->conta->saldo(), 'Crédito e contra-lançamento se anulam na gaveta.');
        $this->assertSame(StatusPagamento::Estornado, $parcela->pagamento->fresh()->status);
    }

    public function test_estorno_de_pix_direto_so_marca_a_baixa_sem_lancamento(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $parcela = $this->parcelaPendente($contexto, 100.00);

        // PIX direto: so a baixa registra o fluxo (sem lancamento, sem caixa).
        $baixa = app(CaixaService::class)->darBaixaParcelaPagamento(
            $parcela, 100.00, $this->formaPagamento($contexto['rede'], TipoFormaPagamento::Pix)
        );

        $this->assertSame(0, Lancamento::count(), 'PIX direto nao gera lançamento.');

        app(CaixaService::class)->estornarPagamento($parcela->pagamento->fresh());

        // Nada a reverter: nao ha lancamento — apenas a marca no fluxo.
        $this->assertSame(0, Lancamento::count(), 'Estorno de PIX direto nao gera contra-lançamento.');
        $this->assertNotNull($baixa->fresh()->estornado_em, 'A baixa e marcada como estornada.');
        $this->assertSame(StatusPagamento::Estornado, $parcela->pagamento->fresh()->status);
    }
}
