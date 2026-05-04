<?php

namespace Tests\Feature\MultiEmpresa;

use App\Enums\StatusCaixa;
use App\Enums\TipoMovimentoEstoque;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Produto\Models\Produto;
use App\Modules\Tenant\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ME-010 v3: valida o middleware AplicarContextoEmpresa e a interacao
 * entre URL `?empresa_id=X` e `session('empresa_contexto_atual')`.
 */
class ContextoEmpresaListagemTest extends TestCase
{
    use RefreshDatabase;

    public function test_url_com_empresa_id_seta_contexto_na_sessao(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empA = $contexto['empresa'];

        $empB = Empresa::create([
            'rede_id' => $rede->id,
            'nome' => 'Empresa B',
        ]);

        session(['empresas_atuais' => [$empA->id, $empB->id]]);

        $resp = $this->get(route('vendas.index', ['empresa_id' => $empB->id]));
        $resp->assertOk();

        $this->assertSame($empB->id, session('empresa_contexto_atual'), 'Contexto deveria ser empresa B apos URL com empresa_id=empB.');
    }

    public function test_url_com_empresa_id_todas_limpa_contexto(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empA = $contexto['empresa'];

        $empB = Empresa::create([
            'rede_id' => $rede->id,
            'nome' => 'Empresa B',
        ]);

        session([
            'empresas_atuais' => [$empA->id, $empB->id],
            'empresa_contexto_atual' => $empB->id,
        ]);

        $resp = $this->get(route('vendas.index', ['empresa_id' => 'todas']));
        $resp->assertOk();

        $this->assertNull(session('empresa_contexto_atual'), 'Contexto deveria ter sido limpo com empresa_id=todas.');
    }

    public function test_url_com_empresa_id_invalido_nao_seta_contexto(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empA = $contexto['empresa'];

        // Cria empresa fora do alcance do usuario (outra rede simulada via id alto).
        $empOutraRede = Empresa::create([
            'rede_id' => $rede->id,
            'nome' => 'Empresa Inacessivel',
        ]);

        // Apenas empA esta em empresas_atuais — empOutraRede nao e acessivel.
        session(['empresas_atuais' => [$empA->id]]);

        $resp = $this->get(route('vendas.index', ['empresa_id' => $empOutraRede->id]));
        $resp->assertOk();

        $this->assertNull(session('empresa_contexto_atual'), 'Contexto nao deveria ser setado para empresa fora de empresas_atuais.');
    }

    public function test_contexto_filtra_global_scope_em_listagens(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empA = $contexto['empresa'];

        $empB = Empresa::create([
            'rede_id' => $rede->id,
            'nome' => 'Empresa B',
        ]);

        session(['empresas_atuais' => [$empA->id, $empB->id]]);

        // Sem contexto: o filtro retorna ambas as empresas.
        $idsSemContexto = Caixa::query()->withoutGlobalScopes()->whereIn('empresa_id', [$empA->id, $empB->id])->pluck('id')->all();
        $this->assertEquals([], $idsSemContexto, 'Sanity: nao ha caixas ainda.');

        // Com contexto = empA: scope filtra so empA.
        session(['empresa_contexto_atual' => $empA->id]);

        $caixaA = Caixa::create([
            'rede_id' => $rede->id,
            'empresa_id' => $empA->id,
            'usuario_id' => $contexto['usuario']->id,
            'data' => today()->toDateString(),
            'saldo_abertura' => 0,
            'status' => StatusCaixa::Aberto,
        ]);

        // Sem contexto, removo via forget para criar caixa em B sem ser filtrado pelo scope.
        session()->forget('empresa_contexto_atual');
        session(['empresa_criacao_atual' => $empB->id]);
        $caixaB = Caixa::create([
            'rede_id' => $rede->id,
            'usuario_id' => $contexto['usuario']->id,
            'data' => today()->toDateString(),
            'saldo_abertura' => 0,
            'status' => StatusCaixa::Aberto,
        ]);
        session()->forget('empresa_criacao_atual');

        $this->assertSame($empB->id, $caixaB->empresa_id, 'Sanity: caixaB deve estar na empresa B.');

        // Reativa contexto = empA e verifica que so empA aparece.
        session(['empresa_contexto_atual' => $empA->id]);
        $idsComContexto = Caixa::query()->pluck('id')->all();
        $this->assertEquals([$caixaA->id], $idsComContexto, 'Com contexto=A, scope deve filtrar apenas caixas de A.');
    }

    public function test_form_create_herda_empresa_do_contexto(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empA = $contexto['empresa'];

        $empB = Empresa::create([
            'rede_id' => $rede->id,
            'nome' => 'Empresa B',
        ]);

        $produto = Produto::create([
            'rede_id' => $rede->id,
            'nome' => 'Produto Teste',
            'valor_venda' => 50.00,
            'valor_custo' => 25.00,
            'quantidade' => 10,
            'ativo' => true,
        ]);

        session([
            'empresas_atuais' => [$empA->id, $empB->id],
            'empresa_contexto_atual' => $empB->id,
        ]);

        $resp = $this->post(route('movimentos-estoque.store'), [
            'produto_id' => $produto->id,
            'tipo' => TipoMovimentoEstoque::Entrada->value,
            'quantidade' => 3,
            // NAO envia empresa_id — herda do contexto via EmpresaTrait.
        ]);

        $resp->assertRedirect(route('movimentos-estoque.index'));

        $movimento = MovimentoEstoque::query()
            ->withoutGlobalScopes()
            ->where('produto_id', $produto->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($movimento);
        $this->assertSame($empB->id, $movimento->empresa_id, 'MovimentoEstoque deveria herdar empresa do contexto.');
    }
}
