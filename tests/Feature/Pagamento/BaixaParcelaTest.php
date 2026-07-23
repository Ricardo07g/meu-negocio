<?php

declare(strict_types=1);

namespace Tests\Feature\Pagamento;

use App\Enums\{CondicaoPagamento, FormaRecebimentoPrazo, StatusCaixa, StatusPagamento, StatusParcela, TipoFormaPagamento};
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Caixa\Services\CaixaService;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Produto\Models\Produto;
use App\Modules\Venda\DTOs\RecebimentoData;
use App\Modules\Venda\Services\VendaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre o caso de baixa parcial em parcela: paga uma de duas, e o
 * pagamento agregado deve ficar em status Parcial — nao Pago, nao
 * Pendente. E baixa real reflete no caixa aberto.
 */
class BaixaParcelaTest extends TestCase
{
    use RefreshDatabase;

    public function test_baixa_parcial_atualiza_status(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $produto = Produto::create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Pacote Mensal',
            'valor_venda' => 200.00,
            'valor_custo' => 100.00,
            'quantidade' => 30,
            'ativo' => true,
        ]);

        Caixa::create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
            'data' => today()->toDateString(),
            'saldo_abertura' => 0,
            'status' => StatusCaixa::Aberto,
        ]);

        app(VendaService::class)->criarVendaProduto(
            cliente_id: null,
            itens: [[
                'produto_id' => $produto->id,
                'quantidade' => 1,
                'valor_unitario' => 200.00,
                'desconto' => 0,
                'acrescimo' => 0,
            ]],
            condicao: CondicaoPagamento::APrazo,
            mesReferencia: Carbon::now()->startOfMonth(),
            // Forma "padrao" (preenche a parcela). A forma real fica na baixa.
            recebimentos: [new RecebimentoData(
                forma: $this->formaPagamento($contexto['rede'], TipoFormaPagamento::Pix),
                valor: 200.00,
            )],
            numeroParcelas: 2,
            primeiroVencimento: Carbon::now()->addMonth()->startOfDay(),
            formaRecebimentoPrazo: FormaRecebimentoPrazo::Carne,
        );

        $pagamento = Pagamento::with('parcelas')->latest('id')->firstOrFail();
        $primeira = $pagamento->parcelas->first();

        app(CaixaService::class)->darBaixaParcelaPagamento(
            parcela: $primeira,
            valor: (float) $primeira->valor,
            forma: $this->formaPagamento($contexto['rede'], TipoFormaPagamento::Pix),
        );

        $primeira->refresh();
        $pagamento->refresh()->load('parcelas');

        $this->assertSame(StatusParcela::Pago, $primeira->status, 'Parcela quitada deveria estar paga.');
        $this->assertSame(StatusPagamento::Parcial, $pagamento->status, 'Com 1 de 2 paga, o titulo deveria ficar Parcial.');

        $segunda = $pagamento->parcelas->last();
        $this->assertSame(StatusParcela::Pendente, $segunda->status, 'A segunda parcela deveria seguir Pendente.');
    }
}
