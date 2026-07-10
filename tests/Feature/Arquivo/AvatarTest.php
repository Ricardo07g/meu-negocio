<?php

declare(strict_types=1);

namespace Tests\Feature\Arquivo;

use App\Modules\Arquivo\Models\Arquivo;
use App\Modules\Arquivo\Services\ArquivoService;
use App\Modules\Cliente\Models\Cliente;
use Database\Factories\ClienteFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cobertura do avatar (imagem unica) via ArquivoService/TemArquivos, usando
 * Cliente como caso representativo, e da genericidade do modulo (arquivo
 * nao-imagem e guardado sem miniatura).
 */
class AvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('r2');
    }

    public function test_criar_cliente_com_foto_gera_original_e_miniatura(): void
    {
        $ctx = $this->criarRedeAutenticada();

        $response = $this->post(route('clientes.store'), [
            'nome' => 'Fulano',
            'foto' => UploadedFile::fake()->image('foto.jpg', 800, 600),
        ]);

        $response->assertRedirect(route('clientes.index'));

        $cliente = Cliente::withoutGlobalScopes()->where('nome', 'Fulano')->firstOrFail();
        $arquivo = $cliente->arquivos()->where('colecao', 'avatar')->firstOrFail();

        $this->assertTrue($arquivo->principal);
        $this->assertTrue($arquivo->ehImagem());
        $this->assertStringContainsString("redes/{$ctx['rede']->id}/clientes/{$cliente->id}/avatar/", $arquivo->caminho);
        Storage::disk('r2')->assertExists($arquivo->caminho);
        Storage::disk('r2')->assertExists($arquivo->caminho_thumb);
    }

    public function test_colecao_unica_substitui_avatar_anterior(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $cliente = ClienteFactory::new()->create(['rede_id' => $ctx['rede']->id]);

        $service = app(ArquivoService::class);
        $primeiro = $service->armazenar($cliente, UploadedFile::fake()->image('a.jpg'), 'avatar');
        $segundo = $service->armazenar($cliente, UploadedFile::fake()->image('b.jpg'), 'avatar');

        $this->assertSame(1, $cliente->arquivos()->where('colecao', 'avatar')->count());
        Storage::disk('r2')->assertMissing($primeiro->caminho);
        Storage::disk('r2')->assertExists($segundo->caminho);
        $this->assertDatabaseMissing('arquivos', ['id' => $primeiro->id]);
    }

    public function test_remover_foto_apaga_registro_e_objeto(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $cliente = ClienteFactory::new()->create(['rede_id' => $ctx['rede']->id]);
        $arquivo = app(ArquivoService::class)->armazenar($cliente, UploadedFile::fake()->image('a.jpg'), 'avatar');

        $response = $this->put(route('clientes.update', $cliente), [
            'nome' => $cliente->nome,
            'remover_foto' => '1',
        ]);

        $response->assertRedirect(route('clientes.index'));
        $this->assertSame(0, $cliente->arquivos()->count());
        Storage::disk('r2')->assertMissing($arquivo->caminho);
    }

    public function test_nao_gera_miniatura_para_arquivo_nao_imagem(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $cliente = ClienteFactory::new()->create(['rede_id' => $ctx['rede']->id]);

        // Colecao 'documentos' nao declarada no model -> usa limites globais
        // (que permitem pdf). Prova que o modulo e generico, nao so imagens.
        $arquivo = app(ArquivoService::class)->armazenar(
            $cliente,
            UploadedFile::fake()->create('manual.pdf', 40, 'application/pdf'),
            'documentos',
        );

        $this->assertFalse($arquivo->ehImagem());
        $this->assertNull($arquivo->caminho_thumb);
        $this->assertSame('application/pdf', $arquivo->mime);
        Storage::disk('r2')->assertExists($arquivo->caminho);
    }

    public function test_atualizar_cliente_de_outra_rede_e_bloqueado(): void
    {
        $this->criarRedeAutenticada(); // rede A (logada)
        $ctxB = $this->criarRede('B');
        $clienteB = ClienteFactory::new()->create(['rede_id' => $ctxB['rede']->id]);

        $response = $this->put(route('clientes.update', $clienteB->id), [
            'nome' => 'Hack',
            'foto' => UploadedFile::fake()->image('a.jpg'),
        ]);

        // Route model binding com escopo rede -> 404 (nao existe para a rede A).
        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseCount('arquivos', 0);
    }
}
