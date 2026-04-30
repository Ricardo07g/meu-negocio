<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Garante que as rotas POST de autenticacao (login e registro) estao
 * protegidas por throttle:5,1 — cinco tentativas por minuto, depois 429.
 *
 * Defesa minima contra brute force/credential stuffing antes de movermos
 * o sistema para producao. Nao substitui CAPTCHA/MFA, mas eleva o custo
 * basico de ataque e e exigido pelo FECH-019.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rate limits sao agregados pelo IP do request — limpamos o
        // bucket usado pela Route::middleware('throttle:5,1') antes de
        // cada teste para que execucoes consecutivas nao se contaminem.
        RateLimiter::clear('127.0.0.1|/login');
        RateLimiter::clear('127.0.0.1|/registrar');
    }

    public function test_login_bloqueia_apos_5_tentativas_por_minuto(): void
    {
        $this->criarRede('rate');

        for ($i = 0; $i < 5; $i++) {
            $this->from(route('login'))->post('/login', [
                'email' => 'desconhecido@teste.com',
                'password' => 'senha-errada',
            ])->assertRedirect(route('login'));
        }

        $resposta = $this->from(route('login'))->post('/login', [
            'email' => 'desconhecido@teste.com',
            'password' => 'senha-errada',
        ]);

        $resposta->assertStatus(429);
    }

    public function test_registrar_bloqueia_apos_5_tentativas_por_minuto(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->from(route('registrar'))->post('/registrar', [
                'rede_nome' => '',
            ]);
        }

        $resposta = $this->from(route('registrar'))->post('/registrar', [
            'rede_nome' => '',
        ]);

        $resposta->assertStatus(429);
    }
}
