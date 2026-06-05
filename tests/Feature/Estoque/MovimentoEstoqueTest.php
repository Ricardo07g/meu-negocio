<?php

namespace Tests\Feature\Estoque;

use App\Enums\TipoMovimentoEstoque;
use App\Modules\Estoque\DTOs\RegistrarMovimentoData;
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Estoque\Services\EstoqueService;
use App\Modules\Produto\Models\Produto;
use App\Modules\Tenant\Models\Empresa;
use Database\Factories\MovimentoEstoqueFactory;
use Database\Factories\ProdutoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Cobre o modulo Estoque (MovimentoEstoque), TRANSACIONAL — isolado por
 * empresa_id (EmpresaTrait) alem de rede_id (RedeTrait/BaseModel).
 *
 * Foco:
 *  - Efeito de cada tipo de movimento no saldo do produto, conforme o
 *    EstoqueService: Entrada incrementa, Saida decrementa, Ajuste define.
 *  - Comportamento real de saida que excede o saldo (o service NAO valida
 *    — permite saldo negativo).
 *  - Isolamento entre redes e entre empresas da mesma rede.
 *  - 403 para papel sem a permissao movimento_estoque.criar.
 *
 * Os testes de saldo chamam EstoqueService diretamente (regra de negocio),
 * enquanto os testes de autorizacao/isolamento usam o endpoint HTTP real
 * (POST /movimentos-estoque), que e a unica rota de escrita do modulo.
 */
class MovimentoEstoqueTest extends TestCase
{
    use RefreshDatabase;

    // ────────────────────────────────────────────────────────────
    // Efeito no saldo do produto
    // ────────────────────────────────────────────────────────────

    public function test_entrada_aumenta_o_saldo_do_produto(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $produto = ProdutoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'quantidade' => 10,
        ]);

        app(EstoqueService::class)->registrarMovimento(
            RegistrarMovimentoData::from([
                'produto_id' => $produto->id,
                'tipo' => TipoMovimentoEstoque::Entrada,
                'quantidade' => 7,
            ])
        );

        $this->assertSame(17, $produto->fresh()->quantidade, 'Entrada deveria somar ao saldo (10 + 7).');

        $this->assertDatabaseHas('movimentos_estoque', [
            'produto_id' => $produto->id,
            'tipo' => TipoMovimentoEstoque::Entrada->value,
            'quantidade' => 7,
            'empresa_id' => $contexto['empresa']->id,
            'rede_id' => $contexto['rede']->id,
        ]);
    }

    public function test_saida_diminui_o_saldo_do_produto(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $produto = ProdutoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'quantidade' => 10,
        ]);

        app(EstoqueService::class)->registrarMovimento(
            RegistrarMovimentoData::from([
                'produto_id' => $produto->id,
                'tipo' => TipoMovimentoEstoque::Saida,
                'quantidade' => 4,
            ])
        );

        $this->assertSame(6, $produto->fresh()->quantidade, 'Saida deveria subtrair do saldo (10 - 4).');

        $this->assertDatabaseHas('movimentos_estoque', [
            'produto_id' => $produto->id,
            'tipo' => TipoMovimentoEstoque::Saida->value,
            'quantidade' => 4,
        ]);
    }

    public function test_ajuste_define_o_saldo_absoluto_do_produto(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $produto = ProdutoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'quantidade' => 10,
        ]);

        // Ajuste NAO soma/subtrai: substitui a quantidade pelo valor informado.
        app(EstoqueService::class)->registrarMovimento(
            RegistrarMovimentoData::from([
                'produto_id' => $produto->id,
                'tipo' => TipoMovimentoEstoque::Ajuste,
                'quantidade' => 3,
            ])
        );

        $this->assertSame(3, $produto->fresh()->quantidade, 'Ajuste deveria sobrescrever o saldo para o valor informado (3).');
    }

    /**
     * Comportamento ATUAL documentado: o EstoqueService NAO valida saldo
     * disponivel na saida. Uma saida maior que o estoque leva o saldo a
     * ficar NEGATIVO (decrement direto, sem guarda). Caso uma regra de
     * bloqueio seja adicionada no futuro, este teste deve ser revisto.
     */
    public function test_saida_maior_que_o_saldo_permite_saldo_negativo(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $produto = ProdutoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'quantidade' => 5,
        ]);

        app(EstoqueService::class)->registrarMovimento(
            RegistrarMovimentoData::from([
                'produto_id' => $produto->id,
                'tipo' => TipoMovimentoEstoque::Saida,
                'quantidade' => 8,
            ])
        );

        $this->assertSame(-3, $produto->fresh()->quantidade, 'Comportamento atual: saida sem validacao deixa o saldo negativo (5 - 8 = -3).');
    }

    // ────────────────────────────────────────────────────────────
    // Endpoint HTTP de criacao (store)
    // ────────────────────────────────────────────────────────────

    public function test_admin_registra_movimento_via_endpoint_e_atualiza_saldo(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $produto = ProdutoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'quantidade' => 20,
        ]);

        $resp = $this->post(route('movimentos-estoque.store'), [
            'produto_id' => $produto->id,
            'tipo' => TipoMovimentoEstoque::Entrada->value,
            'quantidade' => 5,
        ]);

        $resp->assertRedirect(route('movimentos-estoque.index'));
        $resp->assertSessionHas('sucesso');

        $this->assertSame(25, $produto->fresh()->quantidade, 'Saldo deveria refletir a entrada feita via endpoint (20 + 5).');

        $this->assertDatabaseHas('movimentos_estoque', [
            'produto_id' => $produto->id,
            'tipo' => TipoMovimentoEstoque::Entrada->value,
            'quantidade' => 5,
            'empresa_id' => $contexto['empresa']->id,
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // Autorizacao
    // ────────────────────────────────────────────────────────────

    public function test_papel_sem_permissao_recebe_403_ao_registrar_movimento(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empresa = $contexto['empresa'];

        $produto = ProdutoFactory::new()->create([
            'rede_id' => $rede->id,
            'quantidade' => 10,
        ]);

        // Profissional e criado sem permissoes de estoque (somente Admin
        // recebe o catalogo completo no PermissaoSeeder).
        $semPermissao = $this->criarUsuarioComum($rede, $empresa, 'Profissional');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($semPermissao);
        session(['empresas_atuais' => [$empresa->id]]);

        $resp = $this->post(route('movimentos-estoque.store'), [
            'produto_id' => $produto->id,
            'tipo' => TipoMovimentoEstoque::Entrada->value,
            'quantidade' => 5,
        ]);

        $resp->assertForbidden();

        // Nenhum movimento criado e saldo intacto.
        $this->assertDatabaseMissing('movimentos_estoque', [
            'produto_id' => $produto->id,
        ]);
        $this->assertSame(10, $produto->fresh()->quantidade, 'Saldo nao deveria mudar quando o request e barrado por permissao.');
    }

    // ────────────────────────────────────────────────────────────
    // Isolamento multi-tenant (rede) e multi-empresa
    // ────────────────────────────────────────────────────────────

    public function test_movimentos_sao_isolados_entre_redes(): void
    {
        $redeA = $this->criarRede('A');
        $redeB = $this->criarRede('B');

        $produtoA = ProdutoFactory::new()->create([
            'rede_id' => $redeA['rede']->id,
            'quantidade' => 0,
        ]);
        $produtoB = ProdutoFactory::new()->create([
            'rede_id' => $redeB['rede']->id,
            'quantidade' => 0,
        ]);

        $movA = MovimentoEstoqueFactory::new()->entrada()->create([
            'rede_id' => $redeA['rede']->id,
            'empresa_id' => $redeA['empresa']->id,
            'produto_id' => $produtoA->id,
            'quantidade' => 3,
        ]);
        $movB = MovimentoEstoqueFactory::new()->entrada()->create([
            'rede_id' => $redeB['rede']->id,
            'empresa_id' => $redeB['empresa']->id,
            'produto_id' => $produtoB->id,
            'quantidade' => 9,
        ]);

        // --- Logado como admin da Rede A ---
        $this->actingAs($redeA['usuario']);
        session(['empresas_atuais' => [$redeA['empresa']->id]]);

        $movimentosA = MovimentoEstoque::all();
        $this->assertCount(1, $movimentosA, 'Admin da Rede A so deveria ver movimentos da propria rede.');
        $this->assertSame($movA->id, $movimentosA->first()->id);
        $this->assertNull(MovimentoEstoque::find($movB->id), 'Movimento de outra rede deveria ser invisivel via find().');

        // --- Logado como admin da Rede B ---
        $this->actingAs($redeB['usuario']);
        session(['empresas_atuais' => [$redeB['empresa']->id]]);

        $movimentosB = MovimentoEstoque::all();
        $this->assertCount(1, $movimentosB, 'Admin da Rede B so deveria ver movimentos da propria rede.');
        $this->assertSame($movB->id, $movimentosB->first()->id);
        $this->assertNull(MovimentoEstoque::find($movA->id), 'Movimento de outra rede deveria ser invisivel via find().');
    }

    public function test_movimentos_sao_isolados_entre_empresas_da_mesma_rede(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empA = $contexto['empresa'];

        $empB = Empresa::create([
            'rede_id' => $rede->id,
            'nome' => 'Empresa B',
        ]);

        // Produto e catalogo (nivel rede), compartilhado entre empresas.
        $produto = ProdutoFactory::new()->create([
            'rede_id' => $rede->id,
            'quantidade' => 0,
        ]);

        $movEmpA = MovimentoEstoqueFactory::new()->entrada()->create([
            'rede_id' => $rede->id,
            'empresa_id' => $empA->id,
            'produto_id' => $produto->id,
            'quantidade' => 2,
        ]);
        $movEmpB = MovimentoEstoqueFactory::new()->entrada()->create([
            'rede_id' => $rede->id,
            'empresa_id' => $empB->id,
            'produto_id' => $produto->id,
            'quantidade' => 4,
        ]);

        // Contexto restrito a empresa A: o EmpresaTrait deve filtrar so a A.
        session([
            'empresas_atuais' => [$empA->id, $empB->id],
            'empresa_contexto_atual' => $empA->id,
        ]);

        $visiveis = MovimentoEstoque::all();
        $this->assertCount(1, $visiveis, 'Com contexto na Empresa A, so o movimento da Empresa A deveria aparecer.');
        $this->assertSame($movEmpA->id, $visiveis->first()->id);
        $this->assertNull(MovimentoEstoque::find($movEmpB->id), 'Movimento da Empresa B nao deveria ser visivel no contexto da Empresa A.');

        // Trocando o contexto para a Empresa B inverte a visibilidade.
        session(['empresa_contexto_atual' => $empB->id]);

        $visiveisB = MovimentoEstoque::all();
        $this->assertCount(1, $visiveisB, 'Com contexto na Empresa B, so o movimento da Empresa B deveria aparecer.');
        $this->assertSame($movEmpB->id, $visiveisB->first()->id);
        $this->assertNull(MovimentoEstoque::find($movEmpA->id), 'Movimento da Empresa A nao deveria ser visivel no contexto da Empresa B.');
    }
}
