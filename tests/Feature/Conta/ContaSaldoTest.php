<?php

declare(strict_types=1);

namespace Tests\Feature\Conta;

use App\Modules\Conta\Models\Conta;
use Database\Factories\LancamentoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regime "fluxo, nao saldo" (ADR-0011): o saldo de uma conta e apenas
 * saldo_inicial + creditos − debitos (lancamentos). So a gaveta (Caixa) acumula
 * lancamentos na pratica; banco/carteira sao rotulos, sem saldo vivo.
 */
class ContaSaldoTest extends TestCase
{
    use RefreshDatabase;

    public function test_saldo_soma_lancamentos_de_credito_e_debito(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $conta = Conta::where('empresa_id', $contexto['empresa']->id)
            ->where('eh_caixa_padrao', true)
            ->firstOrFail();

        $base = [
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'conta_id' => $conta->id,
        ];

        LancamentoFactory::new()->credito()->create($base + ['valor' => 120.00]);
        LancamentoFactory::new()->debito()->create($base + ['valor' => 20.00]);

        $conta->refresh();

        // saldo_inicial (0) + 120 − 20 = 100.
        $this->assertSame(100.00, $conta->saldo(), 'Saldo = saldo_inicial + creditos − debitos.');
    }
}
