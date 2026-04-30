<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Smoke test da raiz da aplicacao.
 *
 * A rota "/" redireciona usuarios nao autenticados para o login —
 * antes (FECH-006) este teste assertava 200 e por isso era a unica
 * falha da baseline. Refatorado para refletir o comportamento real.
 */
class ExampleTest extends TestCase
{
    public function test_root_redireciona_para_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
