<?php

declare(strict_types=1);

namespace Tests\Feature\Conta;

use App\Enums\{TipoConta, TipoLancamento};
use App\Modules\Conta\Models\{Conta, Lancamento};
use Database\Factories\ContaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fundacao do razao de contas: toda empresa nasce com Caixa + Banco, e o saldo
 * de uma conta e saldo_inicial + creditos − debitos (lancamentos).
 */
class ContaTest extends TestCase
{
    use RefreshDatabase;

    public function test_empresa_nasce_com_contas_padrao(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $contas = Conta::where('empresa_id', $contexto['empresa']->id)->get();

        $this->assertCount(2, $contas, 'Empresa deveria nascer com 2 contas (Caixa + Banco).');

        $caixa = $contas->firstWhere('tipo', TipoConta::Caixa);
        $this->assertNotNull($caixa, 'Deveria existir a conta Caixa.');
        $this->assertTrue($caixa->eh_caixa_padrao);

        $banco = $contas->firstWhere('tipo', TipoConta::Banco);
        $this->assertNotNull($banco, 'Deveria existir a conta Banco.');
        $this->assertTrue($banco->eh_destino_recebivel_padrao);
    }

    public function test_saldo_e_saldo_inicial_mais_creditos_menos_debitos(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $conta = ContaFactory::new()->banco()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'saldo_inicial' => 100.00,
        ]);

        $base = [
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'conta_id' => $conta->id,
            'data' => now()->toDateString(),
            'descricao' => 'Teste',
        ];

        Lancamento::create($base + ['tipo' => TipoLancamento::Credito, 'valor' => 50.00]);
        Lancamento::create($base + ['tipo' => TipoLancamento::Debito, 'valor' => 30.00]);

        // 100 + 50 − 30 = 120
        $this->assertSame(120.00, $conta->fresh()->saldo());
    }
}
