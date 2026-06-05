<?php

namespace Tests\Feature\Despesa;

use App\Enums\StatusDespesa;
use App\Enums\StatusParcela;
use App\Exceptions\NegocioException;
use App\Modules\Despesa\Services\DespesaService;
use Database\Factories\DespesaFactory;
use Database\Factories\ParcelaDespesaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre o cancelamento de despesas e de parcelas individuais no
 * DespesaService:
 *  - Cancelar despesa: parcelas em aberto (Pendente/Renegociado) viram
 *    Cancelado e o titulo vira Cancelada quando nao ha parcela paga.
 *  - Cancelar despesa ja paga / ja cancelada: bloqueado.
 *  - Cancelar parcela avulsa Pendente: marca Cancelado e recalcula titulo.
 *  - Cancelar parcela ja Paga: bloqueado.
 */
class CancelamentoDespesaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelar_despesa_cancela_parcelas_em_aberto(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $despesa = DespesaFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'valor_total' => 200.00,
            'status' => StatusDespesa::Pendente,
        ]);

        $parcelas = collect([1, 2])->map(fn (int $n) => ParcelaDespesaFactory::new()->pendente()->create([
            'despesa_id' => $despesa->id,
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'numero' => $n,
            'total' => 2,
            'valor' => 100.00,
        ]));

        $atualizada = app(DespesaService::class)->cancelarDespesa($despesa, 'Fornecedor cancelou');

        $this->assertSame(StatusDespesa::Cancelada, $atualizada->status, 'Sem parcela paga, o titulo deveria ficar Cancelada.');

        foreach ($parcelas as $parcela) {
            $this->assertSame(StatusParcela::Cancelado, $parcela->fresh()->status, 'Parcela em aberto deveria ser cancelada.');
        }

        $this->assertStringContainsString('Fornecedor cancelou', (string) $parcelas->first()->fresh()->observacao);
    }

    public function test_cancelar_despesa_ja_paga_e_bloqueado(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $despesa = DespesaFactory::new()->paga()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'status' => StatusDespesa::Paga,
        ]);

        $this->expectException(NegocioException::class);
        $this->expectExceptionMessage('Despesa já paga não pode ser cancelada.');

        app(DespesaService::class)->cancelarDespesa($despesa);
    }

    public function test_cancelar_despesa_ja_cancelada_e_bloqueado(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $despesa = DespesaFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'status' => StatusDespesa::Cancelada,
        ]);

        $this->expectException(NegocioException::class);
        $this->expectExceptionMessage('Despesa já está cancelada.');

        app(DespesaService::class)->cancelarDespesa($despesa);
    }

    public function test_cancelar_parcela_pendente_recalcula_titulo(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $despesa = DespesaFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'valor_total' => 200.00,
            'status' => StatusDespesa::Pendente,
        ]);

        // Uma parcela ja paga e uma pendente.
        ParcelaDespesaFactory::new()->paga()->create([
            'despesa_id' => $despesa->id,
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'numero' => 1,
            'total' => 2,
            'valor' => 100.00,
            'valor_pago' => 100.00,
        ]);

        $pendente = ParcelaDespesaFactory::new()->pendente()->create([
            'despesa_id' => $despesa->id,
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'numero' => 2,
            'total' => 2,
            'valor' => 100.00,
        ]);

        $atualizada = app(DespesaService::class)->cancelarParcela($pendente, 'Negociado fora');

        $this->assertSame(StatusParcela::Cancelado, $atualizada->status);
        $this->assertStringContainsString('Negociado fora', (string) $atualizada->observacao);

        // Com a unica parcela ativa restante paga, o titulo vira Paga.
        $this->assertSame(StatusDespesa::Paga, $despesa->fresh()->status, 'Restando so a parcela paga ativa, o titulo deveria ficar Paga.');
    }

    public function test_cancelar_parcela_ja_paga_e_bloqueado(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $despesa = DespesaFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
        ]);

        $paga = ParcelaDespesaFactory::new()->paga()->create([
            'despesa_id' => $despesa->id,
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'numero' => 1,
            'total' => 1,
            'valor' => 100.00,
            'valor_pago' => 100.00,
        ]);

        $this->expectException(NegocioException::class);
        $this->expectExceptionMessage('Parcela já paga não pode ser cancelada.');

        app(DespesaService::class)->cancelarParcela($paga);
    }
}
