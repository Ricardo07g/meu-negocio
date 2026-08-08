<?php

declare(strict_types=1);

namespace Tests\Feature\Arquivo;

use App\Modules\Arquivo\Services\ArquivoService;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Produto\Models\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A imagem enviada sobrevive quando o formulario volta com erro de validacao.
 *
 * Arquivo nao entra no `withInput()`, entao o upload valido e estacionado em
 * tmp/{token} e o caminho viaja pelo old input (App\Traits\PreservaImagemRascunho).
 * Cliente e usado como caso representativo do <x-campo-imagem>; o ultimo teste
 * cobre o caminho paralelo da galeria do Produto.
 */
class ImagemPreservadaNoErroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('r2');
    }

    private function caminhoPreservado(): string
    {
        return (string) session()->getOldInput('foto_rascunho');
    }

    public function test_erro_de_validacao_estaciona_a_imagem_e_devolve_o_caminho(): void
    {
        $this->criarRedeAutenticada();

        $response = $this->from(route('clientes.create'))->post(route('clientes.store'), [
            'nome' => '', // dispara o erro
            'foto' => UploadedFile::fake()->image('foto.jpg', 400, 400),
        ]);

        $response->assertSessionHasErrors('nome');
        $response->assertSessionHasInput('foto_rascunho');

        $caminho = $this->caminhoPreservado();
        $token = (string) session(ArquivoService::SESSAO_TOKEN_UNICO);

        $this->assertNotSame('', $token);
        $this->assertStringContainsString("tmp/{$token}/", $caminho);
        Storage::disk('r2')->assertExists($caminho);

        // Nada foi criado: o erro e o erro.
        $this->assertDatabaseCount('clientes', 0);

        // O que o usuario ve ao voltar: o preview preenchido e o hidden que leva
        // a imagem adiante — sem isso, preservar no servidor nao serviria de nada.
        $form = $this->get(route('clientes.create'));
        $form->assertOk();
        $form->assertSee('name="foto_rascunho"', false);
        $form->assertSee($caminho, false);
    }

    public function test_reenvio_usa_a_imagem_preservada_sem_subir_de_novo(): void
    {
        $this->criarRedeAutenticada();

        $this->from(route('clientes.create'))->post(route('clientes.store'), [
            'nome' => '',
            'foto' => UploadedFile::fake()->image('foto.jpg', 400, 400),
        ]);

        $caminhoTmp = $this->caminhoPreservado();

        // Segundo submit: so o campo corrigido + o caminho do hidden. Sem binario.
        $response = $this->post(route('clientes.store'), [
            'nome' => 'Fulano',
            'foto_rascunho' => $caminhoTmp,
        ]);

        $response->assertRedirect(route('clientes.index'));

        $cliente = Cliente::withoutGlobalScopes()->where('nome', 'Fulano')->firstOrFail();
        $arquivo = $cliente->arquivos()->where('colecao', 'avatar')->firstOrFail();

        $this->assertTrue($arquivo->principal);
        Storage::disk('r2')->assertExists($arquivo->caminho);
        Storage::disk('r2')->assertMissing($caminhoTmp); // foi movido, nao copiado
        $this->assertNull(session(ArquivoService::SESSAO_TOKEN_UNICO));
    }

    public function test_arquivo_novo_vence_a_imagem_preservada(): void
    {
        $this->criarRedeAutenticada();

        $this->from(route('clientes.create'))->post(route('clientes.store'), [
            'nome' => '',
            'foto' => UploadedFile::fake()->image('antiga.jpg', 400, 400),
        ]);

        $caminhoTmp = $this->caminhoPreservado();

        $this->post(route('clientes.store'), [
            'nome' => 'Beltrano',
            'foto_rascunho' => $caminhoTmp,
            'foto' => UploadedFile::fake()->image('nova.jpg', 800, 800),
        ]);

        $cliente = Cliente::withoutGlobalScopes()->where('nome', 'Beltrano')->firstOrFail();
        $arquivo = $cliente->arquivos()->where('colecao', 'avatar')->firstOrFail();

        $this->assertSame(1, $cliente->arquivos()->count());
        $this->assertSame('nova.jpg', $arquivo->nome_original);
        // O rascunho ficou para tras (morre no arquivos:limpar-rascunhos).
        Storage::disk('r2')->assertExists($caminhoTmp);
    }

    public function test_caminho_forjado_de_outro_token_e_ignorado(): void
    {
        $this->criarRedeAutenticada();

        $response = $this->withSession([ArquivoService::SESSAO_TOKEN_UNICO => 'TOKEN-DA-SESSAO'])
            ->post(route('clientes.store'), [
                'nome' => 'Sicrano',
                'foto_rascunho' => 'meu-negocio/tmp/OUTRO-TOKEN/roubada.jpg',
            ]);

        // Nao e 500: o caminho vem do cliente, entao so nao vale nada.
        $response->assertRedirect(route('clientes.index'));

        $cliente = Cliente::withoutGlobalScopes()->where('nome', 'Sicrano')->firstOrFail();
        $this->assertSame(0, $cliente->arquivos()->count());
    }

    public function test_imagem_reprovada_pela_propria_regra_nao_e_estacionada(): void
    {
        $this->criarRedeAutenticada();

        // max:2048 KB na regra de `foto` — reaproveitar so repetiria a recusa.
        $response = $this->from(route('clientes.create'))->post(route('clientes.store'), [
            'nome' => 'Fulano',
            'foto' => UploadedFile::fake()->image('enorme.jpg')->size(3000),
        ]);

        $response->assertSessionHasErrors('foto');
        $this->assertSame('', $this->caminhoPreservado());
        $this->assertEmpty(Storage::disk('r2')->allFiles());
    }

    public function test_galeria_do_produto_mantem_o_token_e_as_imagens_apos_o_erro(): void
    {
        $this->criarRedeAutenticada();

        $this->get(route('produtos.create'))->assertOk();
        $token = (string) session('arquivo_rascunho_token');

        $this->assertNotSame('', $token);

        $rascunho = $this->post(route('produtos.arquivos.rascunho.store'), [
            'arquivo' => UploadedFile::fake()->image('g1.jpg'),
            'token' => $token,
        ])->assertCreated()->json('caminho');

        // Falha de validacao: o produto nao nasce, mas a imagem tem de continuar valendo.
        $this->from(route('produtos.create'))->post(route('produtos.store'), [
            'nome' => '', // unico campo invalido
            'quantidade' => 1,
            'valor_venda' => 29.90,
            'ativo' => true,
            'arquivos_rascunho' => [$rascunho],
        ])->assertSessionHasErrors('nome');

        // O token nao pode ser regenerado ao voltar para o form.
        $this->get(route('produtos.create'))->assertOk();
        $this->assertSame($token, (string) session('arquivo_rascunho_token'));

        $this->post(route('produtos.store'), [
            'nome' => 'Produto A',
            'quantidade' => 1,
            'valor_venda' => 29.90,
            'ativo' => true,
            'arquivos_rascunho' => [$rascunho],
        ])->assertRedirect(route('produtos.index'));

        $produto = Produto::withoutGlobalScopes()->where('nome', 'Produto A')->firstOrFail();
        $this->assertSame(1, $produto->arquivos()->where('colecao', 'galeria')->count());
        Storage::disk('r2')->assertMissing($rascunho);
    }
}
