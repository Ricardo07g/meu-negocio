<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\NegocioException;
use App\Traits\TratamentoErros;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * tratarErro devolvia TODO o input com withInput(): senha e codigo iam parar no
 * payload da sessao (tabela `sessions`) so para repreencher um campo.
 */
class TratamentoErrosTest extends TestCase
{
    public function test_segredos_nao_voltam_no_input_e_o_resto_volta(): void
    {
        $request = Request::create('/qualquer', 'POST', [
            'nome' => 'Maria',
            'password' => 'segredo-1',
            'password_confirmation' => 'segredo-1',
            'senha_atual' => 'segredo-2',
        ]);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        $controller = new class
        {
            use TratamentoErros;

            public function falhar(): mixed
            {
                return $this->tratarErro(new NegocioException('recusado'), 'teste');
            }
        };

        $controller->falhar();

        $devolvido = $this->app['session.store']->get('_old_input');
        $this->assertSame('Maria', $devolvido['nome'] ?? null);
        $this->assertArrayNotHasKey('password', $devolvido);
        $this->assertArrayNotHasKey('password_confirmation', $devolvido);
        $this->assertArrayNotHasKey('senha_atual', $devolvido);
    }
}
