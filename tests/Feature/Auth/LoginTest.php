<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre o fluxo de login: credenciais validas, invalidas e usuario inativo.
 *
 * O LoginController faz logout e devolve mensagem amigavel quando o
 * usuario tenta entrar com a conta desativada — comportamento sensivel
 * que merece teste de regressao.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_credenciais_invalidas_falham(): void
    {
        $this->criarRede('valid');

        $response = $this->from(route('login'))->post('/login', [
            'email' => 'admin'.'valid'.'@teste.com',
            'password' => 'senha-errada',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_usuario_inativo_nao_loga(): void
    {
        $contexto = $this->criarRede('inativo');
        $contexto['usuario']->update(['ativo' => false]);

        $response = $this->from(route('login'))->post('/login', [
            'email' => $contexto['usuario']->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_credenciais_validas_logam_e_redirecionam(): void
    {
        $contexto = $this->criarRede('ok');

        $response = $this->post('/login', [
            'email' => $contexto['usuario']->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($contexto['usuario']);
    }
}
