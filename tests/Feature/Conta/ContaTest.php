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

    public function test_telas_de_listagem_e_cadastro_renderizam(): void
    {
        $this->criarRedeAutenticada();

        $this->get(route('contas.index'))
            ->assertOk()
            ->assertViewIs('conta::index')
            ->assertSee('Caixa'); // conta padrão semeada

        $this->get(route('contas.create'))
            ->assertOk()
            ->assertSee('id="conta-tipo"', false);
    }

    public function test_cria_conta_bancaria(): void
    {
        $this->criarRedeAutenticada();

        $resp = $this->post(route('contas.store'), [
            'nome' => 'Itaú PJ',
            'tipo' => TipoConta::Banco->value,
            'ativo' => 1,
            'saldo_inicial' => 500.00,
            'eh_destino_recebivel_padrao' => 1,
            'instituicao' => 'Itaú',
            'agencia' => '1234',
            'numero' => '56789-0',
        ]);

        $resp->assertRedirect(route('contas.index'));

        $conta = Conta::where('nome', 'Itaú PJ')->firstOrFail();
        $this->assertSame(TipoConta::Banco, $conta->tipo);
        $this->assertSame(500.00, (float) $conta->saldo_inicial);
        $this->assertTrue($conta->eh_destino_recebivel_padrao);
        $this->assertSame('Itaú', $conta->instituicao);
    }

    public function test_conta_caixa_nao_guarda_dados_de_banco(): void
    {
        $this->criarRedeAutenticada();

        $this->post(route('contas.store'), [
            'nome' => 'Cofre',
            'tipo' => TipoConta::Caixa->value,
            'ativo' => 1,
            'eh_destino_recebivel_padrao' => 1, // não se aplica ao caixa
            'instituicao' => 'Banco X',         // idem
        ]);

        $conta = Conta::where('nome', 'Cofre')->firstOrFail();
        $this->assertFalse($conta->eh_destino_recebivel_padrao);
        $this->assertNull($conta->instituicao);
    }

    public function test_apenas_uma_conta_caixa_padrao_por_empresa(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // Já existe a conta "Caixa" padrão semeada. Marcar outra como padrão desmarca a anterior.
        $this->post(route('contas.store'), [
            'nome' => 'Caixa 2',
            'tipo' => TipoConta::Caixa->value,
            'ativo' => 1,
            'eh_caixa_padrao' => 1,
        ]);

        $padroes = Conta::where('empresa_id', $contexto['empresa']->id)
            ->where('eh_caixa_padrao', true)->get();

        $this->assertCount(1, $padroes, 'Só pode haver uma conta-caixa padrão por empresa.');
        $this->assertSame('Caixa 2', $padroes->first()->nome);
    }

    public function test_excluir_conta_faz_soft_delete(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $conta = ContaFactory::new()->banco()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
        ]);

        $this->delete(route('contas.destroy', $conta))
            ->assertRedirect(route('contas.index'));

        $this->assertSoftDeleted('contas', ['id' => $conta->id]);
    }

    public function test_nao_acessa_conta_de_outra_rede(): void
    {
        $this->criarRedeAutenticada();

        $outra = $this->criarRede('outra');
        $contaOutra = ContaFactory::new()->banco()->create([
            'rede_id' => $outra['rede']->id,
            'empresa_id' => $outra['empresa']->id,
        ]);

        // Global scope de rede filtra o binding — conta de outra rede não é encontrada.
        $this->get(route('contas.edit', $contaOutra))->assertNotFound();
    }
}
