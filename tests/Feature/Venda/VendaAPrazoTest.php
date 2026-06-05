<?php

declare(strict_types=1);

namespace Tests\Feature\Venda;

use App\Enums\{CondicaoPagamento, FormaPagamento, FormaRecebimentoPrazo, StatusPagamento, StatusParcela};
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Produto\Models\Produto;
use App\Modules\Venda\Services\VendaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Venda a prazo: gera Pagamento status Pendente + N parcelas Pendentes
 * sem tocar caixa (baixa real acontece quando cada parcela e quitada
 * em Contas a Receber).
 */
class VendaAPrazoTest extends TestCase
{
    use RefreshDatabase;

    public function test_gera_parcelas_pendentes(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $produto = Produto::create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Combo Premium',
            'valor_venda' => 300.00,
            'valor_custo' => 100.00,
            'quantidade' => 50,
            'ativo' => true,
        ]);

        app(VendaService::class)->criarVendaProduto(
            cliente_id: null,
            itens: [[
                'produto_id' => $produto->id,
                'quantidade' => 1,
                'valor_unitario' => 300.00,
                'desconto' => 0,
                'acrescimo' => 0,
            ]],
            condicao: CondicaoPagamento::APrazo,
            mesReferencia: Carbon::now()->startOfMonth(),
            formaAvista: FormaPagamento::Pix,
            numeroParcelas: 3,
            primeiroVencimento: Carbon::now()->addMonth()->startOfDay(),
            formaRecebimentoPrazo: FormaRecebimentoPrazo::Carne,
        );

        $pagamento = Pagamento::with('parcelas')->latest('id')->firstOrFail();

        $this->assertSame(StatusPagamento::Pendente, $pagamento->status, 'Pagamento a prazo deveria nascer Pendente.');
        $this->assertCount(3, $pagamento->parcelas, 'Deveria gerar 3 parcelas.');

        foreach ($pagamento->parcelas as $parcela) {
            $this->assertSame(StatusParcela::Pendente, $parcela->status, 'Toda parcela a prazo deveria ser Pendente.');
            $this->assertSame(0.0, (float) $parcela->valor_pago);
        }

        $somaParcelas = (float) $pagamento->parcelas->sum('valor');
        $this->assertEqualsWithDelta(300.00, $somaParcelas, 0.01, 'Soma das parcelas deveria bater com o total.');
    }
}
