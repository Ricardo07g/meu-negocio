<?php

declare(strict_types=1);

namespace Tests\Feature\Pagamento;

use Database\Factories\{PagamentoFactory, ParcelaPagamentoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * Smoke do GET /pagamentos/{id}/recibo (PagamentoController::recibo).
 *
 * O recibo faz eager-load de relacoes ['cliente','parcelas.baixas',
 * 'agendamento.servico','vendaEtapas.servico','vendaProduto.itens'] — todas
 * tocadas pelo refactor pacote->etapas. Uma relacao/coluna inexistente cairia
 * no tratarErro -> redirect-back com 'erro', entao exigimos 200 + content-type
 * PDF como prova de que o caminho completo (controller + view + metodos do
 * model) executa limpo.
 */
class ReciboSmokeTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    public function test_recibo_de_pagamento_gera_pdf(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $pagamento = PagamentoFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'valor_total' => 300.00,
        ]);
        ParcelaPagamentoFactory::new()->pendente()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'pagamento_id' => $pagamento->id,
            'valor' => 300.00,
        ]);

        $resp = $this->get(route('pagamentos.recibo', ['pagamento' => $pagamento->id]));

        $resp->assertOk();
        $this->assertSame('application/pdf', $resp->headers->get('content-type'));
    }
}
