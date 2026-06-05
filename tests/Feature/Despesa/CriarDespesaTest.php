<?php

declare(strict_types=1);

namespace Tests\Feature\Despesa;

use App\Enums\{CondicaoPagamento, FormaPagamento, FormaRecebimentoPrazo, StatusDespesa, StatusParcela};
use App\Modules\Despesa\DTOs\CriarDespesaData;
use App\Modules\Despesa\Services\DespesaService;
use Carbon\Carbon;
use Database\Factories\CategoriaDespesaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre a criacao de despesas (titulo + parcelas) pelo fluxo real do
 * service/action:
 *  - A vista: gera exatamente 1 parcela pendente com o valor total.
 *  - A prazo: gera N parcelas pendentes cuja soma fecha o valor total.
 *
 * Em ambos os casos a empresa_id e herdada do contexto via EmpresaTrait,
 * confirmando que cascade (Despesa -> ParcelaDespesa) fica na mesma empresa.
 */
class CriarDespesaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_despesa_a_vista_com_uma_parcela(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $categoria = CategoriaDespesaFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
        ]);

        $data = new CriarDespesaData(
            nome: 'Conta de Luz',
            valor_total: 250.00,
            condicao_pagamento: CondicaoPagamento::AVista,
            mes_referencia: Carbon::now()->startOfMonth(),
            data_emissao: Carbon::now(),
            primeiro_vencimento: Carbon::now()->addDays(5),
            categoria_despesa_id: $categoria->id,
            forma_pagamento_avista: FormaPagamento::Dinheiro,
        );

        $despesa = app(DespesaService::class)->criar($data);
        $despesa->load('parcelas');

        $this->assertSame(StatusDespesa::Pendente, $despesa->status);
        $this->assertSame($contexto['empresa']->id, $despesa->empresa_id, 'Despesa deveria herdar a empresa do contexto.');
        $this->assertCount(1, $despesa->parcelas, 'A vista deveria gerar exatamente 1 parcela.');

        $parcela = $despesa->parcelas->first();
        $this->assertSame(StatusParcela::Pendente, $parcela->status);
        $this->assertSame(1, $parcela->numero);
        $this->assertSame(1, $parcela->total);
        $this->assertSame(250.00, (float) $parcela->valor);
        $this->assertSame(0.0, (float) $parcela->valor_pago);
        $this->assertSame($contexto['empresa']->id, $parcela->empresa_id, 'Parcela deveria ficar na mesma empresa da despesa.');
    }

    public function test_cria_despesa_a_prazo_com_n_parcelas_pendentes(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $categoria = CategoriaDespesaFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
        ]);

        $data = new CriarDespesaData(
            nome: 'Reforma',
            valor_total: 900.00,
            condicao_pagamento: CondicaoPagamento::APrazo,
            mes_referencia: Carbon::now()->startOfMonth(),
            data_emissao: Carbon::now(),
            primeiro_vencimento: Carbon::now()->addMonth()->startOfDay(),
            categoria_despesa_id: $categoria->id,
            numero_parcelas: 3,
            forma_pagamento_avista: FormaPagamento::Boleto,
            forma_recebimento_prazo: FormaRecebimentoPrazo::Carne,
        );

        $despesa = app(DespesaService::class)->criar($data);
        $despesa->load('parcelas');

        $this->assertSame(StatusDespesa::Pendente, $despesa->status);
        $this->assertCount(3, $despesa->parcelas, 'A prazo com numero_parcelas=3 deveria gerar 3 parcelas.');

        foreach ($despesa->parcelas as $parcela) {
            $this->assertSame(StatusParcela::Pendente, $parcela->status, 'Toda parcela a prazo nasce Pendente.');
            $this->assertSame(3, $parcela->total);
            $this->assertSame(0.0, (float) $parcela->valor_pago);
            $this->assertSame($contexto['empresa']->id, $parcela->empresa_id);
        }

        $soma = (float) $despesa->parcelas->sum('valor');
        $this->assertEqualsWithDelta(900.00, $soma, 0.01, 'A soma das parcelas deve fechar o valor total.');

        $numeros = $despesa->parcelas->pluck('numero')->sort()->values()->all();
        $this->assertSame([1, 2, 3], $numeros, 'As parcelas devem ser numeradas sequencialmente.');
    }
}
