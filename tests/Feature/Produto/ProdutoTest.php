<?php

declare(strict_types=1);

namespace Tests\Feature\Produto;

use App\Modules\Produto\Models\Produto;
use Database\Factories\ProdutoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Cobertura Feature do modulo Produto (catalogo rede-level, sem empresa_id).
 *
 * Cobre o CRUD via HTTP (criar/listar/editar/excluir), a busca AJAX
 * (GET produtos/buscar), o isolamento multi-tenant (rede A nao ve produto
 * da rede B) e a barreira de permissao (403 para papel sem produto.criar).
 */
class ProdutoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pode_criar_produto(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $response = $this->post(route('produtos.store'), [
            'nome' => 'Shampoo Profissional',
            'codigo' => 'SHP-001',
            'quantidade' => 10,
            'valor_custo' => 12.50,
            'valor_venda' => 29.90,
            'estoque_minimo' => 2,
            'unidade' => 'un',
            'ativo' => true,
        ]);

        $response->assertRedirect(route('produtos.index'));
        $response->assertSessionHas('sucesso');

        $this->assertDatabaseHas('produtos', [
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Shampoo Profissional',
            'codigo' => 'SHP-001',
            'quantidade' => 10,
            'valor_venda' => 29.90,
        ]);
    }

    public function test_criar_produto_exige_nome_quantidade_e_valor_venda(): void
    {
        $this->criarRedeAutenticada();

        $response = $this->from(route('produtos.create'))
            ->post(route('produtos.store'), []);

        $response->assertSessionHasErrors(['nome', 'quantidade', 'valor_venda']);
        $this->assertDatabaseCount('produtos', 0);
    }

    public function test_listagem_index_carrega_com_escopo_de_rede(): void
    {
        $contexto = $this->criarRedeAutenticada();

        ProdutoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Produto da Rede',
        ]);

        $response = $this->get(route('produtos.index'));

        $response->assertOk();
        $response->assertSee('Produto da Rede');
    }

    public function test_admin_pode_editar_produto(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $produto = ProdutoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Nome Antigo',
            'valor_venda' => 10.00,
            'quantidade' => 5,
        ]);

        $response = $this->put(route('produtos.update', $produto), [
            'nome' => 'Nome Novo',
            'quantidade' => 8,
            'valor_venda' => 19.90,
            'ativo' => true,
        ]);

        $response->assertRedirect(route('produtos.index'));

        $this->assertDatabaseHas('produtos', [
            'id' => $produto->id,
            'nome' => 'Nome Novo',
            'quantidade' => 8,
            'valor_venda' => 19.90,
        ]);
    }

    public function test_admin_pode_excluir_produto(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $produto = ProdutoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
        ]);

        $response = $this->delete(route('produtos.destroy', $produto));

        $response->assertRedirect(route('produtos.index'));

        // Produto usa SoftDeletes: deve sumir das queries normais
        // mas continuar na tabela com deleted_at preenchido.
        $this->assertSoftDeleted('produtos', ['id' => $produto->id]);
        $this->assertNull(Produto::find($produto->id));
    }

    public function test_busca_ajax_retorna_apenas_produtos_ativos_que_casam_o_termo(): void
    {
        $contexto = $this->criarRedeAutenticada();

        ProdutoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Condicionador Premium',
            'ativo' => true,
        ]);

        // Produto inativo nao deve aparecer na busca AJAX.
        ProdutoFactory::new()->inativo()->create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Condicionador Antigo',
        ]);

        // Produto que nao casa o termo.
        ProdutoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Escova de Cabelo',
        ]);

        $response = $this->getJson(route('produtos.buscar', ['q' => 'Condicionador']));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['nome' => 'Condicionador Premium']);
        $response->assertJsonMissing(['nome' => 'Condicionador Antigo']);
    }

    public function test_busca_ajax_ignora_termo_curto(): void
    {
        $contexto = $this->criarRedeAutenticada();

        ProdutoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Item Qualquer',
        ]);

        // Termo com menos de 2 caracteres retorna lista vazia.
        $response = $this->getJson(route('produtos.buscar', ['q' => 'I']));

        $response->assertOk();
        $response->assertExactJson([]);
    }

    public function test_isolamento_rede_a_nao_ve_produto_da_rede_b(): void
    {
        $redeA = $this->criarRede('A');
        $redeB = $this->criarRede('B');

        $produtoA = ProdutoFactory::new()->create([
            'rede_id' => $redeA['rede']->id,
            'nome' => 'Produto A',
        ]);

        $produtoB = ProdutoFactory::new()->create([
            'rede_id' => $redeB['rede']->id,
            'nome' => 'Produto B',
        ]);

        // Logado como admin da Rede A.
        $this->actingAs($redeA['usuario']);
        session(['empresas_atuais' => [$redeA['empresa']->id]]);

        $produtos = Produto::all();
        $this->assertCount(1, $produtos);
        $this->assertSame($produtoA->id, $produtos->first()->id);
        $this->assertNull(Produto::find($produtoB->id));

        // Acessar produto de outra rede via rota retorna 404 (model binding
        // filtrado pelo global scope de rede).
        $response = $this->get(route('produtos.show', $produtoB->id));
        $response->assertNotFound();
    }

    public function test_papel_sem_permissao_recebe_403_ao_criar_produto(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // Papel Recepcao apenas com leitura, sem produto.criar.
        $papel = Role::firstOrCreate(['name' => 'Recepcao', 'guard_name' => 'web']);
        $papel->syncPermissions(['produto.ver']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $recepcao = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Recepcao');

        $this->actingAs($recepcao);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $response = $this->post(route('produtos.store'), [
            'nome' => 'Produto Proibido',
            'quantidade' => 1,
            'valor_venda' => 9.90,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('produtos', ['nome' => 'Produto Proibido']);
    }
}
