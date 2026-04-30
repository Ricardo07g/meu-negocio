<?php

namespace Tests\Feature\MultiTenant;

use App\Modules\Cliente\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garante que o RedeTrait isola dados entre redes diferentes.
 *
 * Cenario:
 *  - Rede A cria cliente "Maria"
 *  - Rede B cria cliente "Joao"
 *  - Logado como admin da Rede A, Cliente::all() so retorna "Maria"
 *  - Logado como admin da Rede B, Cliente::all() so retorna "Joao"
 *
 * Tambem verifica que o usuario logado em A nao consegue carregar
 * por id um cliente da rede B (a query e filtrada antes de chegar ao banco).
 */
class IsolamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_rede_a_nao_ve_clientes_rede_b(): void
    {
        $redeA = $this->criarRede('A');
        $redeB = $this->criarRede('B');

        $clienteA = Cliente::create([
            'rede_id' => $redeA['rede']->id,
            'nome' => 'Maria (Rede A)',
            'telefone' => '(11) 90000-0001',
        ]);

        $clienteB = Cliente::create([
            'rede_id' => $redeB['rede']->id,
            'nome' => 'Joao (Rede B)',
            'telefone' => '(11) 90000-0002',
        ]);

        // --- Logado como admin da Rede A ---
        $this->actingAs($redeA['usuario']);
        session(['empresas_atuais' => [$redeA['empresa']->id]]);

        $clientesA = Cliente::all();
        $this->assertCount(1, $clientesA, 'Admin da Rede A so deveria ver clientes da propria rede.');
        $this->assertSame($clienteA->id, $clientesA->first()->id);

        // Tentar buscar cliente da rede B retorna null (filtrado pelo global scope)
        $this->assertNull(Cliente::find($clienteB->id), 'Cliente de outra rede deveria ser invisivel via find().');

        // --- Logado como admin da Rede B ---
        $this->actingAs($redeB['usuario']);
        session(['empresas_atuais' => [$redeB['empresa']->id]]);

        $clientesB = Cliente::all();
        $this->assertCount(1, $clientesB, 'Admin da Rede B so deveria ver clientes da propria rede.');
        $this->assertSame($clienteB->id, $clientesB->first()->id);

        $this->assertNull(Cliente::find($clienteA->id), 'Cliente de outra rede deveria ser invisivel via find().');
    }
}
