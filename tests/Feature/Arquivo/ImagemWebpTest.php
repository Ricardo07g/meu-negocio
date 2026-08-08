<?php

declare(strict_types=1);

namespace Tests\Feature\Arquivo;

use App\Exceptions\NegocioException;
use App\Modules\Arquivo\Services\ArquivoService;
use App\Modules\Cliente\Models\Cliente;
use Database\Factories\ClienteFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Normalizacao de imagens em WebP (ADR-0015).
 *
 * Cobre o que antes quebrava (upload .webp estourava na miniatura, porque o GD
 * era compilado sem --with-webp) e a conversao de jpg/png na gravacao.
 */
class ImagemWebpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('r2');
    }

    public function test_upload_de_webp_gera_original_e_miniatura(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $cliente = ClienteFactory::new()->create(['rede_id' => $ctx['rede']->id]);

        $arquivo = app(ArquivoService::class)->armazenar(
            $cliente,
            UploadedFile::fake()->image('foto.webp', 800, 600),
            'avatar',
        );

        $this->assertSame('image/webp', $arquivo->mime);
        $this->assertNotNull($arquivo->caminho_thumb);
        Storage::disk('r2')->assertExists($arquivo->caminho);
        Storage::disk('r2')->assertExists($arquivo->caminho_thumb);
    }

    public function test_jpg_e_convertido_para_webp_na_gravacao(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $cliente = ClienteFactory::new()->create(['rede_id' => $ctx['rede']->id]);

        $arquivo = app(ArquivoService::class)->armazenar(
            $cliente,
            UploadedFile::fake()->image('foto.jpg', 800, 600),
            'avatar',
        );

        $this->assertSame('webp', $arquivo->extensao);
        $this->assertSame('image/webp', $arquivo->mime);
        $this->assertStringEndsWith('.webp', $arquivo->caminho);
        $this->assertStringEndsWith('_thumb.webp', (string) $arquivo->caminho_thumb);

        // O nome exibido ao usuario continua sendo o que ele enviou.
        $this->assertSame('foto.jpg', $arquivo->nome_original);
    }

    public function test_png_da_galeria_tambem_e_convertido(): void
    {
        $ctx = $this->criarRedeAutenticada();

        $response = $this->post(route('clientes.store'), [
            'nome' => 'Beltrano',
            'foto' => UploadedFile::fake()->image('avatar.png', 400, 400),
        ]);

        $response->assertRedirect(route('clientes.index'));

        $cliente = Cliente::withoutGlobalScopes()->where('nome', 'Beltrano')->firstOrFail();
        $arquivo = $cliente->arquivos()->where('colecao', 'avatar')->firstOrFail();

        $this->assertSame('image/webp', $arquivo->mime);
        $this->assertStringContainsString("redes/{$ctx['rede']->id}/clientes/{$cliente->id}/avatar/", $arquivo->caminho);
    }

    public function test_imagem_grande_e_reduzida_ate_a_largura_maxima(): void
    {
        config(['arquivos.imagem.largura_maxima' => 1600]);

        $ctx = $this->criarRedeAutenticada();
        $cliente = ClienteFactory::new()->create(['rede_id' => $ctx['rede']->id]);

        $arquivo = app(ArquivoService::class)->armazenar(
            $cliente,
            UploadedFile::fake()->image('grande.jpg', 3000, 2000),
            'avatar',
        );

        $this->assertSame(1600, $arquivo->largura);
        $this->assertLessThanOrEqual(1600, (int) $arquivo->altura);
    }

    public function test_tamanho_e_hash_descrevem_o_objeto_gravado(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $cliente = ClienteFactory::new()->create(['rede_id' => $ctx['rede']->id]);

        $arquivo = app(ArquivoService::class)->armazenar(
            $cliente,
            UploadedFile::fake()->image('foto.jpg', 900, 700),
            'avatar',
        );

        $conteudo = (string) Storage::disk('r2')->get($arquivo->caminho);

        $this->assertSame(strlen($conteudo), (int) $arquivo->tamanho);
        $this->assertSame(hash('sha256', $conteudo), $arquivo->hash);
    }

    public function test_arquivo_corrompido_vira_erro_de_negocio_e_nao_deixa_orfao(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $cliente = ClienteFactory::new()->create(['rede_id' => $ctx['rede']->id]);

        $this->expectException(NegocioException::class);

        try {
            app(ArquivoService::class)->armazenar(
                $cliente,
                UploadedFile::fake()->create('quebrada.jpg', 12, 'image/jpeg'),
                'avatar',
            );
        } finally {
            $this->assertSame(0, $cliente->arquivos()->count());
            $this->assertEmpty(Storage::disk('r2')->allFiles());
        }
    }

    public function test_gif_nao_e_convertido_para_preservar_a_animacao(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $cliente = ClienteFactory::new()->create(['rede_id' => $ctx['rede']->id]);

        // Colecao nao declarada -> limites globais de config/arquivos.php (aceitam gif).
        $arquivo = app(ArquivoService::class)->armazenar(
            $cliente,
            UploadedFile::fake()->image('animado.gif', 200, 200),
            'documentos',
        );

        $this->assertSame('gif', $arquivo->extensao);
        $this->assertStringEndsWith('.gif', $arquivo->caminho);
    }

    public function test_conversao_desligada_mantem_o_formato_original(): void
    {
        config(['arquivos.imagem.converter_para_webp' => false]);

        $ctx = $this->criarRedeAutenticada();
        $cliente = ClienteFactory::new()->create(['rede_id' => $ctx['rede']->id]);

        $arquivo = app(ArquivoService::class)->armazenar(
            $cliente,
            UploadedFile::fake()->image('foto.jpg', 300, 300),
            'avatar',
        );

        $this->assertSame('jpg', $arquivo->extensao);
        Storage::disk('r2')->assertExists($arquivo->caminho);
        Storage::disk('r2')->assertExists($arquivo->caminho_thumb);
    }
}
