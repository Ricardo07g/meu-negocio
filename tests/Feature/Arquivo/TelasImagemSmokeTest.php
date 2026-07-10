<?php

declare(strict_types=1);

namespace Tests\Feature\Arquivo;

use App\Modules\Arquivo\Services\ArquivoService;
use Database\Factories\{ClienteFactory, ProdutoFactory, ServicoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Smoke de renderizacao das telas que ganharam UI de imagem (componentes
 * x-thumb / x-campo-imagem, galeria do produto, carrossel). Um erro de Blade
 * viraria 500, entao basta assertar 200.
 */
class TelasImagemSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('r2');
    }

    public function test_telas_de_formulario_e_listagem_renderizam(): void
    {
        $ctx = $this->criarRedeAutenticada();

        $this->get(route('produtos.index'))->assertOk();
        $this->get(route('produtos.create'))->assertOk();
        $this->get(route('clientes.index'))->assertOk();
        $this->get(route('clientes.create'))->assertOk();
        $this->get(route('servicos.index'))->assertOk();
        $this->get(route('servicos.create'))->assertOk();
        $this->get(route('usuarios.index'))->assertOk();
        $this->get(route('usuarios.create'))->assertOk();
        $this->get(route('perfil.index'))->assertOk();
    }

    public function test_edicao_e_detalhe_do_produto_com_imagem_renderizam(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $produto = ProdutoFactory::new()->create(['rede_id' => $ctx['rede']->id]);
        app(ArquivoService::class)->armazenar($produto, UploadedFile::fake()->image('a.jpg'), 'galeria');

        $this->get(route('produtos.edit', $produto))->assertOk();
        $this->get(route('produtos.show', $produto))->assertOk()->assertSee('carouselProduto');
    }

    public function test_detalhe_do_cliente_e_servico_exibe_a_foto(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $service = app(ArquivoService::class);

        $cliente = ClienteFactory::new()->create(['rede_id' => $ctx['rede']->id]);
        $arqCliente = $service->armazenar($cliente, UploadedFile::fake()->image('c.jpg'), 'avatar');
        $this->get(route('clientes.show', $cliente))->assertOk()->assertSee($arqCliente->url, false);

        $servico = ServicoFactory::new()->create(['rede_id' => $ctx['rede']->id]);
        $arqServico = $service->armazenar($servico, UploadedFile::fake()->image('s.jpg'), 'avatar');
        $this->get(route('servicos.show', $servico))->assertOk()->assertSee($arqServico->url, false);
    }
}
