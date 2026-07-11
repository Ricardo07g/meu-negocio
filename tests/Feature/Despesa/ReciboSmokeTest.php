<?php

declare(strict_types=1);

namespace Tests\Feature\Despesa;

use App\Modules\Tenant\Models\Empresa;
use Barryvdh\DomPDF\Facade\Pdf;
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

    /**
     * O comprovante de pagamento deve refletir a empresa DA DESPESA, nao a
     * empresa-padrao de quem imprime.
     */
    public function test_recibo_usa_a_empresa_da_despesa(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $empresaPadrao = $contexto['empresa'];
        $empresaOutra = Empresa::create(['rede_id' => $contexto['rede']->id, 'nome' => 'Filial Norte']);
        session(['empresas_atuais' => [$empresaPadrao->id, $empresaOutra->id]]);

        $categoria = CategoriaDespesaFactory::new()->create(['rede_id' => $contexto['rede']->id]);
        $despesa = DespesaFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $empresaOutra->id,
            'categoria_despesa_id' => $categoria->id,
            'valor_total' => 450.00,
        ]);
        ParcelaDespesaFactory::new()->pendente()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $empresaOutra->id,
            'despesa_id' => $despesa->id,
            'valor' => 450.00,
        ]);

        $capturado = null;
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('stream')->andReturn(response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function ($view, $dados) use (&$capturado, $pdf) {
            $capturado = $dados;

            return $pdf;
        });

        $this->get(route('despesas.recibo', ['despesa' => $despesa->id]))->assertOk();

        $this->assertSame($empresaOutra->id, $capturado['empresa']->id);
        $this->assertNotSame($empresaPadrao->id, $capturado['empresa']->id);
    }
}
