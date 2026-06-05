<?php

declare(strict_types=1);

namespace Tests\Feature\Despesa;

use Database\Factories\{CategoriaDespesaFactory, DespesaFactory, ParcelaDespesaFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * Smoke do GET /despesas/{id}/recibo (DespesaController::recibo).
 *
 * Carrega ['categoria','parcelas.baixas'] e chama metodos do model
 * (valorPago, totalPagoLiquido, saldoRestante) na view. Exige 200 + PDF.
 */
class ReciboSmokeTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    public function test_recibo_de_despesa_gera_pdf(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $categoria = CategoriaDespesaFactory::new()->create(['rede_id' => $contexto['rede']->id]);
        $despesa = DespesaFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'categoria_despesa_id' => $categoria->id,
            'valor_total' => 450.00,
        ]);
        ParcelaDespesaFactory::new()->pendente()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'despesa_id' => $despesa->id,
            'valor' => 450.00,
        ]);

        $resp = $this->get(route('despesas.recibo', ['despesa' => $despesa->id]));

        $resp->assertOk();
        $this->assertSame('application/pdf', $resp->headers->get('content-type'));
    }
}
