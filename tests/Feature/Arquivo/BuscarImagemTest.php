<?php

declare(strict_types=1);

namespace Tests\Feature\Arquivo;

use App\Modules\Arquivo\Services\ArquivoService;
use Database\Factories\ProdutoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Garante que os endpoints de busca AJAX expoem a miniatura da imagem
 * principal (usada nos dropdowns de Venda e Agenda).
 */
class BuscarImagemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('r2');
    }

    public function test_buscar_produto_inclui_miniatura_quando_ha_imagem(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $produto = ProdutoFactory::new()->create(['rede_id' => $ctx['rede']->id, 'nome' => 'Shampoo Premium', 'ativo' => true]);
        app(ArquivoService::class)->armazenar($produto, UploadedFile::fake()->image('s.jpg'), 'galeria');

        $response = $this->getJson(route('produtos.buscar', ['q' => 'Shampoo']));

        $response->assertOk();
        $response->assertJsonCount(1);
        $this->assertNotNull($response->json('0.imagem_thumb_url'));
    }

    public function test_buscar_produto_sem_imagem_retorna_miniatura_nula(): void
    {
        $ctx = $this->criarRedeAutenticada();
        ProdutoFactory::new()->create(['rede_id' => $ctx['rede']->id, 'nome' => 'Condicionador', 'ativo' => true]);

        $response = $this->getJson(route('produtos.buscar', ['q' => 'Condicionador']));

        $response->assertOk();
        $this->assertNull($response->json('0.imagem_thumb_url'));
    }
}
