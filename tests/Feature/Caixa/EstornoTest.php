<?php

declare(strict_types=1);

namespace Tests\Feature\Caixa;

use App\Enums\{CondicaoPagamento, StatusCaixa, StatusPagamento, StatusVendaProduto, TipoFormaPagamento, TipoLancamento};
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Conta\Models\Lancamento;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Produto\Models\Produto;
use App\Modules\Venda\Models\VendaProduto;
use App\Modules\Venda\Services\VendaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estorno automatico ao cancelar venda a vista:
 *  - Venda passa para Cancelada;
 *  - Pagamento passa para Estornado;
 *  - A conta recebe um contra-lancamento (debito) com o valor recebido;
 *  - Estoque e devolvido.
 */
class EstornoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelar_venda_estorna_caixa(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $produto = Produto::create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Loção Hidratante',
            'valor_venda' => 80.00,
            'valor_custo' => 40.00,
            'quantidade' => 20,
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

        $service = app(VendaService::class);

        $venda = $service->criarVendaProduto(
            cliente_id: null,
            itens: [[
                'produto_id' => $produto->id,
                'quantidade' => 1,
                'valor_unitario' => 80.00,
                'desconto' => 0,
                'acrescimo' => 0,
            ]],
            condicao: CondicaoPagamento::AVista,
            mesReferencia: Carbon::now()->startOfMonth(),
            formaAvista: $this->formaPagamento($contexto['rede'], TipoFormaPagamento::Dinheiro),
            numeroParcelas: null,
            primeiroVencimento: now(),
        );

        $service->cancelarVendaProduto($venda);

        $vendaFresh = VendaProduto::find($venda->id);
        $this->assertSame(StatusVendaProduto::Cancelada, $vendaFresh->status);

        $pagamento = Pagamento::where('venda_produto_id', $venda->id)->firstOrFail();
        $this->assertSame(StatusPagamento::Estornado, $pagamento->status);

        // Um credito (recebimento) e um debito (estorno) — o saldo da conta fecha em 0.
        $creditos = Lancamento::where('tipo', TipoLancamento::Credito)->sum('valor');
        $debitos = Lancamento::where('tipo', TipoLancamento::Debito)->sum('valor');

        $this->assertSame(80.00, (float) $creditos);
        $this->assertSame(80.00, (float) $debitos, 'Cancelar venda paga deveria gerar débito de estorno igual ao recebido.');

        $this->assertSame(20, $produto->fresh()->quantidade, 'Estoque deveria ter sido devolvido.');
    }
}
