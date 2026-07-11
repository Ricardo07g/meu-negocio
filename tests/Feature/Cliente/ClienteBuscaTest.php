<?php

declare(strict_types=1);

namespace Tests\Feature\Cliente;

use Carbon\Carbon;
use Database\Factories\ClienteFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobertura da busca AJAX de clientes (GET clientes/buscar).
 *
 * Trava o contrato do payload consumido pelo card de selecao de cliente na
 * tela de nova venda: alem de id/nome/telefone, a busca precisa devolver os
 * dados de perfil (email, cpf, sexo, nascimento + idade, endereco).
 */
class ClienteBuscaTest extends TestCase
{
    use RefreshDatabase;

    public function test_busca_ajax_retorna_dados_completos_do_cliente_para_o_card(): void
    {
        $contexto = $this->criarRedeAutenticada();

        ClienteFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Maria Aparecida',
            'telefone' => '11999998888',
            'telefone_whatsapp' => true,
            'email' => 'maria@teste.com',
            'cpf' => '12345678901',
            'sexo' => 'F',
            'data_nascimento' => '1990-05-10',
            'cidade' => 'Campinas',
            'estado' => 'SP',
        ]);

        $response = $this->getJson(route('clientes.buscar', ['q' => 'Maria']));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'nome' => 'Maria Aparecida',
            'telefone' => '11999998888',
            'telefone_whatsapp' => true,
            'email' => 'maria@teste.com',
            'cpf' => '12345678901',
            'sexo' => 'F',
            'data_nascimento' => '10/05/1990',
            'idade' => Carbon::parse('1990-05-10')->age,
            'cidade' => 'Campinas',
            'estado' => 'SP',
        ]);
    }

    public function test_busca_ajax_ignora_termo_curto(): void
    {
        $contexto = $this->criarRedeAutenticada();

        ClienteFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Cliente Qualquer',
        ]);

        $response = $this->getJson(route('clientes.buscar', ['q' => 'C']));

        $response->assertOk();
        $response->assertExactJson([]);
    }
}
