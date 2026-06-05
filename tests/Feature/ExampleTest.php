<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * Smoke test da raiz da aplicacao.
 *
 * A rota "/" passou a servir a landing page publica (antes apenas redirecionava
 * para o login). Usuario autenticado e enviado direto ao dashboard.
 */
class ExampleTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    public function test_root_exibe_landing_para_visitante(): void
    {
        // A landing usa @vite; sem manifest (a CI nao builda assets) o Vite
        // lancaria excecao. withoutVite() neutraliza as tags no teste.
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('landing');
    }

    public function test_root_redireciona_usuario_logado_para_dashboard(): void
    {
        $this->criarRedeAutenticada();

        $response = $this->get('/');

        $response->assertRedirect(route('dashboard'));
    }
}
