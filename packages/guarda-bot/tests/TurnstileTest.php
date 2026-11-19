<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Tests;

use Illuminate\Support\Facades\Http;
use Ricardo07g\GuardaBot\Turnstile;

class TurnstileTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->post('/formulario', fn () => response('ok'))->middleware(['web', 'guarda-bot.turnstile']);
    }

    private function configurar(): void
    {
        config([
            'guarda-bot.turnstile.site_key' => 'chave-publica-de-teste',
            'guarda-bot.turnstile.secret_key' => 'chave-secreta-de-teste',
        ]);
    }

    /**
     * @param  array<string, mixed>  $corpo
     */
    private function responder(array $corpo, int $status = 200): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response($corpo, $status)]);
    }

    public function test_sem_chaves_fica_desligado_e_nao_toca_a_rede(): void
    {
        Http::fake();

        $this->assertFalse(Turnstile::estaAtivo());
        $this->assertTrue(Turnstile::tokenValido(null));
        $this->post('/formulario')->assertOk();

        Http::assertNothingSent();
    }

    public function test_token_ausente_e_recusado(): void
    {
        $this->configurar();
        Http::fake();

        $this->from('/entrar')->post('/formulario')
            ->assertRedirect('/entrar')
            ->assertSessionHasErrors(Turnstile::CAMPO);

        Http::assertNothingSent();
    }

    public function test_token_recusado_pela_cloudflare_e_barrado_sem_devolver_a_senha(): void
    {
        $this->configurar();
        $this->responder(['success' => false]);

        $this->from('/entrar')
            ->post('/formulario', [Turnstile::CAMPO => 'token', 'email' => 'a@b.c', 'password' => 'segredo'])
            ->assertRedirect('/entrar')
            ->assertSessionHasErrors(Turnstile::CAMPO)
            ->assertSessionMissing('_old_input.password');
    }

    public function test_token_aceito_segue_e_envia_secret_token_e_ip(): void
    {
        $this->configurar();
        $this->responder(['success' => true]);

        $this->post('/formulario', [Turnstile::CAMPO => 'token-bom'])->assertOk();

        Http::assertSent(fn ($r): bool => $r['secret'] === 'chave-secreta-de-teste'
            && $r['response'] === 'token-bom'
            && $r['remoteip'] === '127.0.0.1');
    }

    public function test_cloudflare_fora_do_ar_libera_a_requisicao(): void
    {
        $this->configurar();
        $this->responder([], 504);

        $this->post('/formulario', [Turnstile::CAMPO => 'token'])->assertOk();
    }

    public function test_secret_aceita_pela_cloudflare_e_valida(): void
    {
        $this->responder(['success' => false, 'error-codes' => ['invalid-input-response']]);

        $this->assertTrue(Turnstile::secretValida('secret-boa'));
    }

    public function test_secret_recusada_pela_cloudflare_e_invalida(): void
    {
        $this->responder(['success' => false, 'error-codes' => ['invalid-input-secret']]);

        $this->assertFalse(Turnstile::secretValida('secret-errada'));
    }

    public function test_validacao_da_secret_sem_rede_e_indeterminada(): void
    {
        $this->responder([], 503);

        $this->assertNull(Turnstile::secretValida('qualquer'));
    }
}
