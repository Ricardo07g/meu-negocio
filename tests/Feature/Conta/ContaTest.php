<?php

declare(strict_types=1);

namespace Tests\Feature\Conta;

use App\Enums\{TipoConta, TipoFormaPagamento, TipoLancamento};
use App\Modules\Conta\Models\{Conta, Lancamento};
use Database\Factories\{ContaFactory, LancamentoFactory};
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
            ->assertSee('name="tipo"', false)
            ->assertDontSee('Caixa (dinheiro físico)'); // tipo Caixa nao e ofertado ao lojista
    }

    public function test_cria_conta_bancaria(): void
    {
        $this->criarRedeAutenticada();

        $resp = $this->post(route('contas.store'), [
            'nome' => 'Itaú PJ',
            'tipo' => TipoConta::Banco->value,
            'ativo' => 1,
            'saldo_inicial' => 500.00,
            'instituicao' => 'Itaú',
            'agencia' => '1234',
            'numero' => '56789-0',
        ]);

        $resp->assertRedirect(route('contas.index'));

        $conta = Conta::where('nome', 'Itaú PJ')->firstOrFail();
        $this->assertSame(TipoConta::Banco, $conta->tipo);
        $this->assertSame(500.00, (float) $conta->saldo_inicial);
        // Destino-recebivel-padrao e interno (so o seed marca) — conta nova nasce sem a flag.
        $this->assertFalse($conta->eh_destino_recebivel_padrao);
        $this->assertSame('Itaú', $conta->instituicao);
    }

    public function test_nao_permite_criar_conta_tipo_caixa(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $this->post(route('contas.store'), [
            'nome' => 'Caixa 2',
            'tipo' => TipoConta::Caixa->value,
            'ativo' => 1,
        ])->assertSessionHasErrors('tipo');

        $this->assertDatabaseMissing('contas', ['nome' => 'Caixa 2']);

        // Continua havendo exatamente uma conta Caixa por empresa (a do sistema).
        $this->assertSame(1, Conta::where('empresa_id', $contexto['empresa']->id)
            ->where('tipo', TipoConta::Caixa)->count());
    }

    public function test_conta_caixa_nao_pode_ser_excluida(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $caixa = $this->contaCaixa($contexto['empresa']->id);

        $this->delete(route('contas.destroy', $caixa));

        $this->assertDatabaseHas('contas', ['id' => $caixa->id, 'deleted_at' => null]);
    }

    public function test_conta_caixa_pode_ser_renomeada_mas_nao_muda_de_tipo(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $caixa = $this->contaCaixa($contexto['empresa']->id);

        $this->put(route('contas.update', $caixa), [
            'nome' => 'Caixa Loja',
            'tipo' => TipoConta::Banco->value, // deve ser ignorado
        ])->assertRedirect(route('contas.index'));

        $caixa->refresh();
        $this->assertSame('Caixa Loja', $caixa->nome);
        $this->assertSame(TipoConta::Caixa, $caixa->tipo);
        $this->assertTrue($caixa->ativo);
    }

    public function test_nao_exclui_conta_com_movimentacao(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $conta = ContaFactory::new()->banco()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
        ]);

        LancamentoFactory::new()->credito()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'conta_id' => $conta->id,
            'valor' => 10.00,
        ]);

        $this->delete(route('contas.destroy', $conta));

        $this->assertDatabaseHas('contas', ['id' => $conta->id, 'deleted_at' => null]);
    }

    public function test_nao_exclui_conta_vinculada_a_forma(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $conta = ContaFactory::new()->banco()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
        ]);

        $this->formaPagamento($contexto['rede'], TipoFormaPagamento::CartaoCredito)
            ->update(['conta_destino_id' => $conta->id]);

        $this->delete(route('contas.destroy', $conta));

        $this->assertDatabaseHas('contas', ['id' => $conta->id, 'deleted_at' => null]);
    }

    private function contaCaixa(int $empresaId): Conta
    {
        return Conta::where('empresa_id', $empresaId)
            ->where('tipo', TipoConta::Caixa)
            ->firstOrFail();
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

    public function test_extrato_da_conta_renderiza_lancamentos(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // Lançamentos (saldo) vivem na conta Caixa (gaveta); banco/carteira mostram
        // recebimentos por forma (fluxo, sem lançamento — ADR-0011).
        $conta = Conta::where('empresa_id', $contexto['empresa']->id)
            ->where('eh_caixa_padrao', true)
            ->firstOrFail();

        LancamentoFactory::new()->credito()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'conta_id' => $conta->id,
            'valor' => 100.00,
            'descricao' => 'Recebimento venda 42',
        ]);

        $this->get(route('contas.extrato', $conta))
            ->assertOk()
            ->assertViewIs('conta::extrato')
            ->assertSee('Recebimento venda 42');
    }

    public function test_extrato_filtra_pelo_mes(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $conta = Conta::where('empresa_id', $contexto['empresa']->id)
            ->where('eh_caixa_padrao', true)->firstOrFail();

        // Lançamento dois meses atrás (sempre em outro mês que o atual).
        $outroMes = now()->subMonthsNoOverflow(2)->startOfMonth();
        LancamentoFactory::new()->credito()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'conta_id' => $conta->id,
            'valor' => 100.00,
            'data' => $outroMes->copy()->addDays(9)->toDateString(),
            'descricao' => 'Lancamento antigo',
        ]);

        // Mês atual (default): não mostra o antigo.
        $this->get(route('contas.extrato', $conta))
            ->assertOk()
            ->assertDontSee('Lancamento antigo');

        // Navegando para o mês do lançamento: aparece.
        $this->get(route('contas.extrato', ['conta' => $conta, 'mes' => $outroMes->format('Y-m')]))
            ->assertOk()
            ->assertSee('Lancamento antigo');
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
