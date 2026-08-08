<?php

declare(strict_types=1);

namespace Tests\Feature\Caixa;

use App\Enums\{CondicaoPagamento, StatusCaixa, StatusPagamento, TipoFormaPagamento};
use App\Modules\Caixa\Models\{BaixaPagamento, Caixa, Recebivel};
use App\Modules\Conta\Models\Lancamento;
use App\Modules\Produto\Models\Produto;
use App\Modules\Venda\DTOs\RecebimentoData;
use App\Modules\Venda\Models\VendaProduto;
use App\Modules\Venda\Services\VendaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Venda no cartao no regime "fluxo, nao saldo" (ADR-0011): quita o cliente na
 * hora e registra o recebimento como BaixaPagamento (recebido por forma do dia),
 * SEM tocar a gaveta (sem lancamento), SEM recebivel e SEM exigir caixa aberto.
 * Cancelar a venda marca a baixa como estornada, sem saida na gaveta.
 */
class VendaCartaoTest extends TestCase
{
    use RefreshDatabase;

    /** Dinheiro exige caixa aberto (conta destino = gaveta); cartao/pix nao. */
    private function abrirCaixa(array $contexto): void
    {
        Caixa::create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
            'data' => today()->toDateString(),
            'saldo_abertura' => 0,
            'status' => StatusCaixa::Aberto,
        ]);
    }

    private function venderNoCartao(array $contexto, float $valor, TipoFormaPagamento $tipo, ?int $parcelasCartao = null): VendaProduto
    {
        $produto = Produto::create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Combo',
            'valor_venda' => $valor,
            'valor_custo' => $valor / 2,
            'quantidade' => 10,
            'ativo' => true,
        ]);

        return app(VendaService::class)->criarVendaProduto(
            cliente_id: null,
            itens: [[
                'produto_id' => $produto->id,
                'quantidade' => 1,
                'valor_unitario' => $valor,
                'desconto' => 0,
                'acrescimo' => 0,
            ]],
            condicao: CondicaoPagamento::AVista,
            mesReferencia: Carbon::now()->startOfMonth(),
            recebimentos: [new RecebimentoData(
                forma: $this->formaPagamento($contexto['rede'], $tipo),
                valor: $valor,
                parcelas_cartao: $parcelasCartao,
            )],
        );
    }

    public function test_venda_cartao_credito_registra_baixa_sem_lancamento_e_sem_recebivel(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // Sem caixa aberto de propósito: cartão não exige caixa.
        $venda = $this->venderNoCartao($contexto, 300.00, TipoFormaPagamento::CartaoCredito, 3);

        // Cliente quitado na hora.
        $this->assertSame(StatusPagamento::Pago, $venda->pagamento->fresh()->status);

        // Recebido registrado como baixa (o painel do dia le por forma).
        $this->assertSame(1, BaixaPagamento::count(), 'O recebimento no cartão vira uma baixa.');
        $this->assertSame(300.00, (float) BaixaPagamento::first()->valorTotal());

        // Fluxo, nao saldo: nada na gaveta, nada de recebivel.
        $this->assertSame(0, Lancamento::count(), 'Cartão não toca a gaveta (sem lançamento).');
        $this->assertSame(0, Recebivel::count(), 'Cartão não gera mais recebível (ADR-0011).');
    }

    /**
     * Regressao: o "2x/3x" escolhido na venda atravessava JS -> Request -> Controller
     * -> DTO -> Service e era DESCARTADO no motor de baixa (o antigo lar era
     * `recebiveis.parcela_total`, aposentado pelo ADR-0011 sem substituto). O lojista
     * escolhia o parcelamento e nunca mais o via em tela nenhuma.
     */
    public function test_venda_cartao_credito_grava_o_parcelamento_na_baixa(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $this->venderNoCartao($contexto, 300.00, TipoFormaPagamento::CartaoCredito, 3);

        $baixa = BaixaPagamento::firstOrFail();
        $this->assertSame(3, $baixa->parcelas_cartao, 'O parcelamento do cartão é persistido na baixa.');
        $this->assertStringContainsString('3x', $baixa->rotuloForma());
    }

    public function test_forma_sem_parcelamento_nao_grava_parcelas_cartao(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->abrirCaixa($contexto);

        // Dinheiro nao tem permite_parcelas: nao deve inventar "1x" nem herdar valor.
        $this->venderNoCartao($contexto, 80.00, TipoFormaPagamento::Dinheiro, 3);

        $baixa = BaixaPagamento::firstOrFail();
        $this->assertNull($baixa->parcelas_cartao, 'Forma sem parcelamento não grava parcelas_cartao.');
        $this->assertSame('Dinheiro', $baixa->rotuloForma());
    }

    public function test_cartao_a_vista_nao_poluí_o_rotulo_com_1x(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $this->venderNoCartao($contexto, 50.00, TipoFormaPagamento::CartaoCredito, 1);

        $baixa = BaixaPagamento::firstOrFail();
        $this->assertNull($baixa->parcelas_cartao, '1x é à vista: não é parcelamento.');
        $this->assertSame('Cartão de Crédito', $baixa->rotuloForma());
    }

    /**
     * Regressao: `totalRecebidoLiquido()` somava TODAS as baixas, inclusive as
     * estornadas — o detalhe da venda cancelada exibia "Recebido R$ 300,00" ao lado
     * da mesma baixa riscada como "Estornado".
     */
    public function test_baixa_estornada_nao_conta_como_recebido(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $venda = $this->venderNoCartao($contexto, 300.00, TipoFormaPagamento::CartaoCredito, 2);
        $pagamento = $venda->pagamento;

        $this->assertSame(300.00, $pagamento->load('parcelas.baixas')->totalRecebidoLiquido());

        app(VendaService::class)->cancelarVendaProduto($venda);

        $this->assertSame(
            0.0,
            $pagamento->fresh()->load('parcelas.baixas')->totalRecebidoLiquido(),
            'Venda cancelada não tem valor recebido.'
        );
    }

    public function test_venda_cartao_debito_nao_exige_caixa(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $venda = $this->venderNoCartao($contexto, 100.00, TipoFormaPagamento::CartaoDebito);

        $this->assertSame(StatusPagamento::Pago, $venda->pagamento->fresh()->status);
        $this->assertSame(1, BaixaPagamento::count());
        $this->assertSame(0, Lancamento::count());
    }

    public function test_cancelar_venda_no_cartao_marca_baixa_estornada_sem_saida_na_gaveta(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $venda = $this->venderNoCartao($contexto, 300.00, TipoFormaPagamento::CartaoCredito, 3);
        $baixa = BaixaPagamento::firstOrFail();

        app(VendaService::class)->cancelarVendaProduto($venda);

        $this->assertNotNull($baixa->fresh()->estornado_em, 'A baixa do cartão é marcada como estornada.');
        $this->assertSame(0, Lancamento::count(), 'Estorno de cartão não gera lançamento (nem entrada nem saída).');
        $this->assertSame(StatusPagamento::Estornado, $venda->pagamento->fresh()->status);
    }
}
