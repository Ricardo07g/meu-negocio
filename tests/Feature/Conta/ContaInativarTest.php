<?php

declare(strict_types=1);

namespace Tests\Feature\Conta;

use App\Enums\{TipoConta, TipoFormaPagamento};
use App\Modules\Conta\Models\Conta;
use Database\Factories\ContaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trilhos de inativacao: a conta comum inativa/reativa; a Caixa do sistema nao;
 * e uma conta usada por forma ATIVA nao pode ser inativada sem trocar o destino.
 */
class ContaInativarTest extends TestCase
{
    use RefreshDatabase;

    public function test_inativa_e_reativa_conta_banco(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $conta = ContaFactory::new()->banco()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'ativo' => true,
        ]);

        $this->patch(route('contas.inativar', $conta))->assertRedirect(route('contas.index'));
        $this->assertFalse($conta->fresh()->ativo);

        $this->patch(route('contas.reativar', $conta))->assertRedirect(route('contas.index'));
        $this->assertTrue($conta->fresh()->ativo);
    }

    public function test_nao_inativa_conta_caixa(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $caixa = Conta::where('empresa_id', $contexto['empresa']->id)
            ->where('tipo', TipoConta::Caixa)->firstOrFail();

        $this->patch(route('contas.inativar', $caixa));

        $this->assertTrue($caixa->fresh()->ativo, 'A conta Caixa do sistema nao pode ser inativada.');
    }

    public function test_nao_inativa_conta_usada_por_forma_ativa(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $conta = ContaFactory::new()->banco()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'ativo' => true,
        ]);

        $this->formaPagamento($contexto['rede'], TipoFormaPagamento::CartaoCredito)
            ->update(['conta_destino_id' => $conta->id, 'ativo' => true]);

        $this->patch(route('contas.inativar', $conta));

        $this->assertTrue($conta->fresh()->ativo, 'Conta com forma ativa vinculada nao inativa.');
    }

    public function test_inativa_conta_usada_apenas_por_forma_inativa(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $conta = ContaFactory::new()->banco()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'ativo' => true,
        ]);

        $this->formaPagamento($contexto['rede'], TipoFormaPagamento::CartaoCredito)
            ->update(['conta_destino_id' => $conta->id, 'ativo' => false]);

        $this->patch(route('contas.inativar', $conta))->assertRedirect(route('contas.index'));

        $this->assertFalse($conta->fresh()->ativo);
    }
}
