<?php

declare(strict_types=1);

namespace Tests\Feature\Pagamento;

use App\Modules\Tenant\Models\Empresa;
use Barryvdh\DomPDF\Facade\Pdf;
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

    /**
     * O comprovante de recebimento deve refletir a empresa DO TITULO, nao a
     * empresa-padrao de quem imprime.
     */
    public function test_recibo_usa_a_empresa_do_titulo(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $empresaPadrao = $contexto['empresa'];
        $empresaOutra = Empresa::create(['rede_id' => $contexto['rede']->id, 'nome' => 'Filial Norte']);
        session(['empresas_atuais' => [$empresaPadrao->id, $empresaOutra->id]]);

        $pagamento = PagamentoFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $empresaOutra->id,
            'valor_total' => 300.00,
        ]);
        ParcelaPagamentoFactory::new()->pendente()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $empresaOutra->id,
            'pagamento_id' => $pagamento->id,
            'valor' => 300.00,
        ]);

        $capturado = null;
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('stream')->andReturn(response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function ($view, $dados) use (&$capturado, $pdf) {
            $capturado = $dados;

            return $pdf;
        });

        $this->get(route('pagamentos.recibo', ['pagamento' => $pagamento->id]))->assertOk();

        $this->assertSame($empresaOutra->id, $capturado['empresa']->id);
        $this->assertNotSame($empresaPadrao->id, $capturado['empresa']->id);
    }
}
