<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * Apos deslogar o usuario deve voltar para a landing page publica (rota 'home'),
 * nao mais para o login.
 */
class LogoutTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    public function test_logout_redireciona_para_a_landing(): void
    {
        $this->criarRedeAutenticada();

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
