<?php

declare(strict_types=1);

namespace Tests\Feature\Pagamento;

use App\Enums\{StatusPagamento, StatusVendaProduto};
use App\Modules\Venda\Models\VendaProduto;
use Database\Factories\PagamentoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

class ContasAReceberTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    /**
     * Regressao: a tela "Contas a Receber" (pagamentos.index -> PagamentoService::listar)
     * fazia eager-load de uma relacao inexistente ('vendaPacote', renomeada para
     * 'vendaEtapas' no refactor). O erro so dispara com >=1 pagamento no escopo
     * autenticado, por isso criamos um na rede/empresa do usuario logado.
     */
    public function test_contas_a_receber_carrega_a_listagem_com_pagamentos(): void
    {
        $contexto = $this->criarRedeAutenticada();

        PagamentoFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
        ]);

        $resp = $this->get(route('pagamentos.index'));

        $resp->assertOk();
        $resp->assertViewIs('pagamento::index');
        $resp->assertViewHas('pagamentos');
    }

    /**
     * Regressao: o pagamento de uma venda cancelada (titulo Estornado, sem
     * parcela a receber) exibia um menu de tres pontinhos vazio. Agora o menu
     * sempre oferece "Ver detalhes da venda" apontando para a tela de detalhes.
     */
    public function test_pagamento_de_venda_cancelada_oferece_ver_detalhes(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $venda = VendaProduto::create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
            'data' => now()->format('Y-m-d'),
            'subtotal' => 100.00,
            'desconto' => 0,
            'acrescimo' => 0,
            'valor_total' => 100.00,
            'status' => StatusVendaProduto::Cancelada,
        ]);

        PagamentoFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'venda_produto_id' => $venda->id,
            'status' => StatusPagamento::Estornado,
        ]);

        $resp = $this->get(route('pagamentos.index'));

        $resp->assertOk();
        $resp->assertSee('Ver detalhes da venda');
        $resp->assertSee(route('vendas.show', ['produto', $venda->id]), false);
    }
}
