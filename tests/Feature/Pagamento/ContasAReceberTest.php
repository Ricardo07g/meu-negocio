<?php

namespace Tests\Feature\Pagamento;

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
}
