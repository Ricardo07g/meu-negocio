<?php

declare(strict_types=1);

namespace Tests\Feature\Arquivo;

use App\Modules\Arquivo\Services\ArquivoService;
use App\Modules\Produto\Models\Produto;
use Database\Factories\ProdutoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cobertura da galeria de imagens do Produto: staging (criacao), upload direto
 * (edicao), reordenar, definir capa, excluir, limite da colecao, binding de
 * token por sessao e isolamento multi-tenant.
 */
class GaleriaProdutoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('r2');
    }

    public function test_fluxo_criacao_move_rascunho_para_o_produto(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $token = 'tok-'.uniqid();

        $rascunho = $this->withSession(['arquivo_rascunho_token' => $token])
            ->post(route('produtos.arquivos.rascunho.store'), [
                'arquivo' => UploadedFile::fake()->image('g1.jpg'),
                'token' => $token,
            ]);

        $rascunho->assertCreated();
        $caminhoTmp = $rascunho->json('caminho');
        $this->assertStringContainsString("tmp/{$token}/", $caminhoTmp);
        Storage::disk('r2')->assertExists($caminhoTmp);

        $store = $this->withSession(['arquivo_rascunho_token' => $token])
            ->post(route('produtos.store'), [
                'nome' => 'Produto com foto',
                'quantidade' => 5,
                'valor_venda' => 19.90,
                'ativo' => true,
                'arquivos_rascunho' => [$caminhoTmp],
            ]);

        $store->assertRedirect(route('produtos.index'));

        $produto = Produto::withoutGlobalScopes()->where('nome', 'Produto com foto')->firstOrFail();
        $arquivo = $produto->arquivos()->firstOrFail();

        $this->assertSame('galeria', $arquivo->colecao);
        $this->assertTrue($arquivo->principal);
        $this->assertStringContainsString("redes/{$ctx['rede']->id}/produtos/{$produto->id}/galeria/", $arquivo->caminho);
        Storage::disk('r2')->assertExists($arquivo->caminho);
        Storage::disk('r2')->assertMissing($caminhoTmp);
    }

    public function test_upload_direto_na_edicao(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $produto = ProdutoFactory::new()->create(['rede_id' => $ctx['rede']->id]);

        $response = $this->post(route('produtos.arquivos.store', $produto), [
            'arquivo' => UploadedFile::fake()->image('a.jpg'),
        ]);

        $response->assertCreated();
        $this->assertSame(1, $produto->arquivos()->count());
    }

    public function test_reordenar_atualiza_ordem_e_capa(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $produto = ProdutoFactory::new()->create(['rede_id' => $ctx['rede']->id]);
        $service = app(ArquivoService::class);

        $a = $service->armazenar($produto, UploadedFile::fake()->image('1.jpg'), 'galeria');
        $b = $service->armazenar($produto, UploadedFile::fake()->image('2.jpg'), 'galeria');

        $this->assertTrue($a->fresh()->principal);

        $this->patch(route('produtos.arquivos.reordenar', $produto), ['ids' => [$b->id, $a->id]])
            ->assertOk();

        $this->assertTrue($b->fresh()->principal);
        $this->assertFalse($a->fresh()->principal);
        $this->assertSame(0, $b->fresh()->ordem);
    }

    public function test_definir_capa_traz_imagem_para_frente(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $produto = ProdutoFactory::new()->create(['rede_id' => $ctx['rede']->id]);
        $service = app(ArquivoService::class);
        $service->armazenar($produto, UploadedFile::fake()->image('1.jpg'), 'galeria');
        $b = $service->armazenar($produto, UploadedFile::fake()->image('2.jpg'), 'galeria');

        $this->patch(route('produtos.arquivos.principal', [$produto, $b]))->assertOk();

        $this->assertTrue($b->fresh()->principal);
        $this->assertSame(0, $b->fresh()->ordem);
    }

    public function test_excluir_imagem_remove_registro_e_objeto(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $produto = ProdutoFactory::new()->create(['rede_id' => $ctx['rede']->id]);
        $arquivo = app(ArquivoService::class)
            ->armazenar($produto, UploadedFile::fake()->image('1.jpg'), 'galeria');

        $this->delete(route('produtos.arquivos.destroy', [$produto, $arquivo]))->assertOk();

        $this->assertDatabaseMissing('arquivos', ['id' => $arquivo->id]);
        Storage::disk('r2')->assertMissing($arquivo->caminho);
    }

    public function test_limite_da_colecao_e_respeitado(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $produto = ProdutoFactory::new()->create(['rede_id' => $ctx['rede']->id]);

        for ($i = 0; $i < 8; $i++) {
            $produto->arquivos()->create([
                'rede_id' => $ctx['rede']->id,
                'colecao' => 'galeria',
                'disco' => 'r2',
                'caminho' => "meu-negocio/x/{$i}.jpg",
                'nome_original' => "{$i}.jpg",
                'extensao' => 'jpg',
                'mime' => 'image/jpeg',
                'tamanho' => 10,
                'ordem' => $i,
                'principal' => $i === 0,
            ]);
        }

        $response = $this->post(route('produtos.arquivos.store', $produto), [
            'arquivo' => UploadedFile::fake()->image('nona.jpg'),
        ]);

        $response->assertStatus(422);
        $this->assertSame(8, $produto->arquivos()->count());
    }

    public function test_token_de_outra_sessao_e_rejeitado(): void
    {
        $this->criarRedeAutenticada();

        $this->withSession(['arquivo_rascunho_token' => 'DA-SESSAO'])
            ->post(route('produtos.arquivos.rascunho.store'), [
                'arquivo' => UploadedFile::fake()->image('x.jpg'),
                'token' => 'OUTRO-TOKEN',
            ])
            ->assertForbidden();
    }

    public function test_nao_anexa_imagem_em_produto_de_outra_rede(): void
    {
        $this->criarRedeAutenticada(); // rede A
        $ctxB = $this->criarRede('B');
        $produtoB = ProdutoFactory::new()->create(['rede_id' => $ctxB['rede']->id]);

        $this->post(route('produtos.arquivos.store', $produtoB->id), [
            'arquivo' => UploadedFile::fake()->image('a.jpg'),
        ])->assertNotFound();

        $this->assertDatabaseCount('arquivos', 0);
    }
}
