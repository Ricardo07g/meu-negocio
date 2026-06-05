<?php

declare(strict_types=1);

namespace Tests\Feature\MultiEmpresa;

use App\Enums\TipoMovimentoEstoque;
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Produto\Models\Produto;
use App\Modules\Tenant\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garante que MovimentoEstoque criado via formulario com 2+ empresas
 * selecionadas usa a empresa escolhida no sub-seletor (`empresa_id` no
 * request) atraves do override `empresa_criacao_atual` no controller.
 */
class MovimentoEstoqueComMultiplasEmpresasTest extends TestCase
{
    use RefreshDatabase;

    public function test_movimento_estoque_usa_empresa_escolhida_no_sub_seletor(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empA = $contexto['empresa'];

        $empB = Empresa::create([
            'rede_id' => $rede->id,
            'nome' => 'Empresa B',
        ]);

        $produto = Produto::create([
            'rede_id' => $rede->id,
            'nome' => 'Produto Teste',
            'valor_venda' => 100.00,
            'valor_custo' => 50.00,
            'quantidade' => 10,
            'ativo' => true,
        ]);

        session(['empresas_atuais' => [$empA->id, $empB->id]]);

        $resp = $this->post(route('movimentos-estoque.store'), [
            'produto_id' => $produto->id,
            'tipo' => TipoMovimentoEstoque::Entrada->value,
            'quantidade' => 5,
            'empresa_id' => $empB->id,
        ]);

        $resp->assertRedirect(route('movimentos-estoque.index'));

        $movimento = MovimentoEstoque::query()
            ->withoutGlobalScopes()
            ->where('produto_id', $produto->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($movimento, 'MovimentoEstoque deveria ter sido criado.');
        $this->assertSame($empB->id, $movimento->empresa_id, 'MovimentoEstoque deve usar a empresa escolhida no sub-seletor.');
        $this->assertNull(session('empresa_criacao_atual'), 'empresa_criacao_atual deveria ter sido limpa no finally.');
    }
}
