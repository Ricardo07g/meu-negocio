<?php

namespace Tests\Feature\Despesa;

use App\Modules\Despesa\Models\Despesa;
use App\Modules\Tenant\Models\Empresa;
use Database\Factories\DespesaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Despesa e transacional: isolada por rede (RedeTrait) e por empresa
 * (EmpresaTrait). Garante que:
 *  - Admin de uma rede nao enxerga despesas de outra rede.
 *  - Dentro da mesma rede, o contexto de empresa filtra as despesas de
 *    outra empresa (multi-empresa N:N).
 */
class IsolamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_despesa_nao_vaza_entre_redes(): void
    {
        $redeA = $this->criarRede('A');
        $redeB = $this->criarRede('B');

        $despesaA = DespesaFactory::new()->create([
            'rede_id' => $redeA['rede']->id,
            'empresa_id' => $redeA['empresa']->id,
            'nome' => 'Aluguel A',
        ]);

        $despesaB = DespesaFactory::new()->create([
            'rede_id' => $redeB['rede']->id,
            'empresa_id' => $redeB['empresa']->id,
            'nome' => 'Aluguel B',
        ]);

        // --- Admin da Rede A ---
        $this->actingAs($redeA['usuario']);
        session(['empresas_atuais' => [$redeA['empresa']->id]]);

        $despesas = Despesa::all();
        $this->assertCount(1, $despesas, 'Admin da Rede A so deveria ver despesas da propria rede.');
        $this->assertSame($despesaA->id, $despesas->first()->id);
        $this->assertNull(Despesa::find($despesaB->id), 'Despesa de outra rede deveria ser invisivel via find().');

        // --- Admin da Rede B ---
        $this->actingAs($redeB['usuario']);
        session(['empresas_atuais' => [$redeB['empresa']->id]]);

        $this->assertSame($despesaB->id, Despesa::all()->first()->id);
        $this->assertNull(Despesa::find($despesaA->id), 'Despesa de outra rede deveria ser invisivel via find().');
    }

    public function test_despesa_isolada_por_empresa_na_mesma_rede(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $outraEmpresa = Empresa::create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Filial 2',
        ]);

        $daEmpresaAtual = DespesaFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'nome' => 'Conta da matriz',
        ]);

        $daOutraEmpresa = DespesaFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $outraEmpresa->id,
            'nome' => 'Conta da filial',
        ]);

        // Contexto da listagem fixado na empresa atual: EmpresaTrait filtra.
        session(['empresa_contexto_atual' => $contexto['empresa']->id]);

        $despesas = Despesa::all();
        $this->assertCount(1, $despesas, 'Com contexto fixado, so a empresa atual deveria aparecer.');
        $this->assertSame($daEmpresaAtual->id, $despesas->first()->id);
        $this->assertNull(Despesa::find($daOutraEmpresa->id), 'Despesa de outra empresa nao deveria ser carregavel no contexto fixado.');
    }
}
