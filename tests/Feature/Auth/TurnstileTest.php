<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Support\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Turnstile nos formularios publicos (login, registro, recuperacao de senha).
 *
 * O eixo mais importante e o **desligado por padrao**: sem chave configurada, o
 * recurso tem de sumir por completo — nenhuma tela muda, nenhuma requisicao sai
 * para a rede. E o que mantem dev, CI e o resto da suite funcionando.
 */
class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    private function configurar(): void
    {
        config([
            'services.turnstile.site_key' => 'chave-publica-de-teste',
            'services.turnstile.secret_key' => 'chave-secreta-de-teste',
        ]);
    }

    private function responder(bool $sucesso): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => $sucesso]),
        ]);
    }

    // ------------------------------------------------------------------
    // Desligado quando nao configurado
    // ------------------------------------------------------------------

    public function test_sem_chave_o_recurso_fica_desligado(): void
    {
        Http::fake();

        $this->assertFalse(Turnstile::estaAtivo());
        $this->assertTrue(Turnstile::tokenValido(null), 'Sem chave, nada deve ser exigido.');

        Http::assertNothingSent();
    }

    public function test_sem_chave_o_widget_nao_aparece_no_login(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('cf-turnstile', escape: false)
            ->assertDontSee('challenges.cloudflare.com', escape: false);
    }

    public function test_sem_chave_o_login_funciona_normalmente(): void
    {
        $contexto = $this->criarRede();

        $this->post(route('login'), [
            'email' => $contexto['usuario']->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
    }

    // ------------------------------------------------------------------
    // Ligado
    // ------------------------------------------------------------------

    public function test_com_chave_o_widget_aparece_nas_tres_telas(): void
    {
        $this->configurar();

        foreach (['login', 'registrar', 'senha.solicitar'] as $rota) {
            $this->get(route($rota))
                ->assertOk()
                ->assertSee('cf-turnstile', escape: false)
                ->assertSee('chave-publica-de-teste', escape: false);
        }
    }

    public function test_login_sem_token_e_recusado(): void
    {
        $this->configurar();
        Http::fake();
        $contexto = $this->criarRede();

        $this->post(route('login'), [
            'email' => $contexto['usuario']->email,
            'password' => 'password',
        ])->assertSessionHasErrors(Turnstile::CAMPO);

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_login_com_token_recusado_pelo_cloudflare_falha(): void
    {
        $this->configurar();
        $this->responder(false);
        $contexto = $this->criarRede();

        $this->post(route('login'), [
            'email' => $contexto['usuario']->email,
            'password' => 'password',
            Turnstile::CAMPO => 'token-de-robo',
        ])->assertSessionHasErrors(Turnstile::CAMPO);

        $this->assertGuest();
    }

    public function test_login_com_token_aceito_passa(): void
    {
        $this->configurar();
        $this->responder(true);
        $contexto = $this->criarRede();

        $this->post(route('login'), [
            'email' => $contexto['usuario']->email,
            'password' => 'password',
            Turnstile::CAMPO => 'token-de-gente',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_registro_exige_o_token(): void
    {
        $this->configurar();
        Http::fake();

        $this->post(route('registrar'), [
            'nome' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => 'senha12345',
            'password_confirmation' => 'senha12345',
            'empresa' => 'Estudio Fulano',
        ])->assertSessionHasErrors(Turnstile::CAMPO);

        $this->assertDatabaseMissing('usuarios', ['email' => 'fulano@example.com']);
    }

    public function test_recuperacao_de_senha_exige_o_token(): void
    {
        $this->configurar();
        Http::fake();

        $this->post(route('senha.solicitar.enviar'), ['email' => 'alguem@example.com'])
            ->assertSessionHasErrors(Turnstile::CAMPO);
    }

    // ------------------------------------------------------------------
    // Indisponibilidade do Cloudflare nao pode derrubar o login
    // ------------------------------------------------------------------

    public function test_cloudflare_fora_do_ar_libera_a_requisicao(): void
    {
        $this->configurar();
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response('gateway timeout', 504),
        ]);

        $this->assertTrue(
            Turnstile::tokenValido('token-qualquer'),
            'Derrubar o login de todos porque o Cloudflare piscou seria pior que o bot que o widget evita.',
        );
    }
}
