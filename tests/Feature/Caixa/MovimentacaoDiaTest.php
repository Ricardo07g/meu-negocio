<?php

declare(strict_types=1);

namespace Tests\Feature\Caixa;

use App\Enums\{CondicaoPagamento, StatusCaixa, StatusDespesa, TipoFormaPagamento, TipoMovimentacaoDia};
use App\Modules\Caixa\Models\{BaixaPagamento, Caixa};
use App\Modules\Caixa\Services\{CaixaService, MovimentacaoDiaService};
use App\Modules\Conta\Models\{Conta, Lancamento};
use App\Modules\Despesa\Models\Despesa;
use App\Modules\Produto\Models\Produto;
use App\Modules\Venda\DTOs\RecebimentoData;
use App\Modules\Venda\Models\VendaProduto;
use App\Modules\Venda\Services\VendaService;
use Carbon\Carbon;
use Database\Factories\{DespesaFactory, PagamentoFactory, ParcelaDespesaFactory, ParcelaPagamentoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Timeline "Movimentações do dia" (ADR-0014): a tela do Caixa mostra o FLUXO do dia
 * da loja — venda recebida, título a prazo baixado, despesa paga, estorno, sangria e
 * reforço — e não mais o razão da conta-caixa (que é o extrato da Conta).
 *
 * O risco estrutural aqui é a DUPLICAÇÃO: um recebimento em dinheiro existe duas vezes
 * no banco (BaixaPagamento + Lancamento de crédito). Se a timeline lesse as duas fontes
 * inteiras, dobraria o dia. Ver test_recebimento_em_dinheiro_nao_duplica_na_timeline.
 */
class MovimentacaoDiaTest extends TestCase
{
    use RefreshDatabase;

    private function timeline(): array
    {
        return app(MovimentacaoDiaService::class)->doDia(today()->toDateString());
    }

    /** Dinheiro exige caixa aberto (conta destino = gaveta); cartão/pix não. */
    private function abrirCaixa(array $contexto, float $abertura = 0.0): Caixa
    {
        return Caixa::create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
            'conta_id' => Conta::where('empresa_id', $contexto['empresa']->id)
                ->where('eh_caixa_padrao', true)
                ->value('id'),
            'data' => today()->toDateString(),
            'saldo_abertura' => $abertura,
            'status' => StatusCaixa::Aberto,
        ]);
    }

    private function venderProduto(array $contexto, float $valor, TipoFormaPagamento $tipo): VendaProduto
    {
        $produto = Produto::create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Combo '.$valor,
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
            )],
        );
    }

    private function pagarDespesa(array $contexto, float $valor, TipoFormaPagamento $tipo, string $nome = 'Aluguel'): Despesa
    {
        $despesa = DespesaFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'nome' => $nome,
            'fornecedor_nome' => 'Imobiliária Central',
            'valor_total' => $valor,
            'status' => StatusDespesa::Pendente,
        ]);

        $parcela = ParcelaDespesaFactory::new()->pendente()->create([
            'despesa_id' => $despesa->id,
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'numero' => 1,
            'total' => 1,
            'valor' => $valor,
        ]);

        app(CaixaService::class)->darBaixaParcelaDespesa(
            parcela: $parcela,
            valor: $valor,
            forma: $this->formaPagamento($contexto['rede'], $tipo),
        );

        return $despesa;
    }

    public function test_timeline_reune_venda_cartao_venda_dinheiro_e_despesa_paga(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->abrirCaixa($contexto);

        $this->venderProduto($contexto, 45.00, TipoFormaPagamento::CartaoCredito);
        $this->venderProduto($contexto, 30.00, TipoFormaPagamento::Dinheiro);
        $this->pagarDespesa($contexto, 40.00, TipoFormaPagamento::Dinheiro);

        $timeline = $this->timeline();

        $this->assertCount(3, $timeline['linhas'], 'Duas vendas e uma despesa = 3 linhas.');
        $this->assertSame(75.00, $timeline['totalEntradas'], 'Entradas do dia somam as duas vendas, em qualquer forma.');
        $this->assertSame(40.00, $timeline['totalSaidas']);
        $this->assertSame(35.00, $timeline['resultado']);
        $this->assertSame(2, $timeline['qtdRecebimentos']);
        $this->assertSame(1, $timeline['qtdDespesas']);

        $tipos = array_map(fn (array $l) => $l['tipo'], $timeline['linhas']);
        $this->assertContains(TipoMovimentacaoDia::Venda, $tipos);
        $this->assertContains(TipoMovimentacaoDia::Despesa, $tipos);

        // A venda no cartão não toca a gaveta; a em dinheiro e a despesa, sim.
        $cartao = collect($timeline['linhas'])->firstWhere('valor', 45.00);
        $this->assertFalse($cartao['tocaGaveta'], 'Cartão vai para a conta da maquineta, não para a gaveta.');
        $this->assertSame('Cartão de Crédito', $cartao['forma']);

        $despesa = collect($timeline['linhas'])->firstWhere('tipo', TipoMovimentacaoDia::Despesa);
        $this->assertTrue($despesa['tocaGaveta']);
        $this->assertSame('Aluguel', $despesa['titulo']);
        $this->assertStringContainsString('Imobiliária Central', (string) $despesa['detalhe']);
    }

    /**
     * Regressao estrutural: venda em dinheiro grava UMA BaixaPagamento **e** UM
     * Lancamento de credito na gaveta. A timeline le as vendas pelo eixo das baixas e
     * so pega `sangria`/`reforco` do eixo dos lancamentos — se lesse os dois inteiros,
     * o dia apareceria dobrado.
     */
    public function test_recebimento_em_dinheiro_nao_duplica_na_timeline(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->abrirCaixa($contexto);

        $this->venderProduto($contexto, 30.00, TipoFormaPagamento::Dinheiro);

        $this->assertSame(1, BaixaPagamento::count());
        $this->assertSame(1, Lancamento::where('categoria', 'movimento')->count(), 'Dinheiro gera lançamento na gaveta.');

        $timeline = $this->timeline();

        $this->assertCount(1, $timeline['linhas'], 'O mesmo recebimento não pode aparecer duas vezes.');
        $this->assertSame(30.00, $timeline['totalEntradas']);
    }

    public function test_sangria_e_reforco_aparecem_mas_nao_entram_no_resultado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $caixa = $this->abrirCaixa($contexto);
        $service = app(CaixaService::class);

        $service->registrarReforco($caixa, 50.00, 'Troco inicial');
        $service->registrarSangria($caixa, 20.00, 'Depósito bancário');

        $timeline = $this->timeline();

        $tipos = array_map(fn (array $l) => $l['tipo'], $timeline['linhas']);
        $this->assertContains(TipoMovimentacaoDia::Reforco, $tipos);
        $this->assertContains(TipoMovimentacaoDia::Sangria, $tipos);

        // Dinheiro trocando de lugar (gaveta <-> banco/bolso) nao e receita nem despesa.
        $this->assertSame(0.0, $timeline['totalEntradas'], 'Reforço não é receita do dia.');
        $this->assertSame(0.0, $timeline['totalSaidas'], 'Sangria não é despesa do dia.');
        $this->assertSame(0.0, $timeline['resultado']);
        $this->assertSame(0, $timeline['qtdRecebimentos']);
    }

    public function test_estorno_entra_como_saida_e_neta_o_resultado(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $venda = $this->venderProduto($contexto, 45.00, TipoFormaPagamento::CartaoCredito);
        app(VendaService::class)->cancelarVendaProduto($venda);

        $timeline = $this->timeline();

        // Dois eventos no mesmo dia: a venda entrou e foi desfeita. Some as duas linhas
        // em vez de apagar a venda do dia (mesma leitura do ResumoDiaService).
        $this->assertCount(2, $timeline['linhas']);
        $this->assertSame(45.00, $timeline['totalEntradas']);
        $this->assertSame(45.00, $timeline['totalSaidas']);
        $this->assertSame(0.0, $timeline['resultado'], 'Vendido e estornado no mesmo dia neta a zero.');

        $venda_ = collect($timeline['linhas'])->firstWhere('tipo', TipoMovimentacaoDia::Venda);
        $this->assertTrue($venda_['estornada'], 'A linha da venda fica marcada como estornada.');
    }

    /** Baixa de parcela de titulo a prazo nao e "uma venda de hoje": e um recebimento. */
    public function test_baixa_de_titulo_a_prazo_aparece_como_recebimento_com_a_parcela(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->abrirCaixa($contexto);

        $pagamento = PagamentoFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'valor_total' => 200.00,
        ]);

        $parcela = ParcelaPagamentoFactory::new()->pendente()->create([
            'pagamento_id' => $pagamento->id,
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'numero' => 2,
            'total' => 3,
            'valor' => 100.00,
        ]);

        app(CaixaService::class)->darBaixaParcelaPagamento(
            parcela: $parcela,
            valor: 100.00,
            forma: $this->formaPagamento($contexto['rede'], TipoFormaPagamento::Dinheiro),
        );

        $linha = collect($this->timeline()['linhas'])->firstWhere('tipo', TipoMovimentacaoDia::Recebimento);

        $this->assertNotNull($linha, 'Título a prazo baixado é Recebimento, não Venda.');
        $this->assertStringContainsString('Parcela 2/3', (string) $linha['detalhe']);
    }

    public function test_timeline_isola_por_empresa(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->venderProduto($contexto, 45.00, TipoFormaPagamento::CartaoCredito);

        // Segunda empresa na MESMA rede, com recebimento proprio.
        $empresaB = $this->criarEmpresaExtra($contexto['rede']->id, 'Filial B');
        $pagamentoB = PagamentoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $empresaB->id,
            'valor_total' => 999.00,
        ]);
        $parcelaB = ParcelaPagamentoFactory::new()->create([
            'pagamento_id' => $pagamentoB->id,
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $empresaB->id,
            'valor' => 999.00,
        ]);
        BaixaPagamento::create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $empresaB->id,
            'parcela_pagamento_id' => $parcelaB->id,
            'valor' => 999.00,
            'multa' => 0,
            'juros' => 0,
            'desconto' => 0,
            'forma_pagamento_nome' => 'Dinheiro',
            'data' => today()->toDateString(),
        ]);

        $timeline = $this->timeline();

        $this->assertCount(1, $timeline['linhas']);
        $this->assertSame(45.00, $timeline['totalEntradas'], 'Recebimento da Filial B (999) não entra no contexto da empresa A.');
    }

    /**
     * O cenario que gerou tudo isso: venda no cartao, nenhum caixa aberto. Antes a
     * metade de baixo da tela era so "Nenhum caixa registrado neste dia" e a venda
     * do dia ficava invisivel.
     */
    public function test_tela_do_caixa_mostra_a_timeline_sem_caixa_aberto(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->venderProduto($contexto, 45.00, TipoFormaPagamento::CartaoCredito);

        $this->get(route('caixas.index'))
            ->assertOk()
            ->assertViewIs('caixa::index')
            ->assertSee('Movimentações do dia')
            ->assertSee('Entradas do dia')
            ->assertSee('Cartão de Crédito')
            ->assertSee('Venda no balcão')
            ->assertSee('Nenhum caixa aberto neste dia')
            // O razao da gaveta saiu desta tela: era a mesma tabela do extrato da conta.
            ->assertDontSee('Movimentos da gaveta');
    }

    /**
     * A marca de gaveta na linha tem de fechar com o saldo do bloco de fechamento —
     * e o que torna auditavel a coexistencia dos dois eixos na mesma tela.
     */
    public function test_linhas_da_gaveta_reconciliam_com_o_saldo_calculado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $caixa = $this->abrirCaixa($contexto, 100.00);

        $this->venderProduto($contexto, 30.00, TipoFormaPagamento::Dinheiro);
        $this->venderProduto($contexto, 45.00, TipoFormaPagamento::CartaoCredito); // não toca a gaveta
        $this->pagarDespesa($contexto, 40.00, TipoFormaPagamento::Dinheiro);
        app(CaixaService::class)->registrarReforco($caixa, 50.00, 'Troco');

        $daGaveta = collect($this->timeline()['linhas'])->where('tocaGaveta', true);

        $entradas = $daGaveta->filter(fn (array $l): bool => $l['tipo']->ehEntrada())->sum('valor');
        $saidas = $daGaveta->reject(fn (array $l): bool => $l['tipo']->ehEntrada())->sum('valor');

        $this->assertSame(
            $caixa->fresh()->saldoCalculado(),
            round(100.00 + $entradas - $saidas, 2),
            'Abertura + Σ(linhas da gaveta) tem de bater com o saldo da sessão.'
        );
    }

    /**
     * REMOVER a venda (nao so cancelar) aplica soft delete no titulo; a baixa NAO
     * tem SoftDeletes e fica para tras. Sem `withTrashed()` na espinha do eager
     * loading, `$baixa->parcela->pagamento` volta null e a timeline explode
     * inteira — a tela do Caixa vira erro 500, nao uma linha sem rotulo.
     *
     * Cobre a regressao de dividir esse eager loading em dois `with()`: o segundo
     * registra `parcela`/`parcela.pagamento` como no-op e sobrescreve os closures
     * do primeiro, perdendo o `withTrashed` em silencio.
     */
    public function test_timeline_sobrevive_a_venda_removida_com_titulo_apagado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->abrirCaixa($contexto);

        $venda = $this->venderProduto($contexto, 30.00, TipoFormaPagamento::CartaoCredito);
        app(VendaService::class)->removerVendaProduto($venda);

        $timeline = $this->timeline();

        $this->assertNotEmpty($timeline['linhas'], 'A venda removida segue no dia (venda + estorno).');

        $estorno = collect($timeline['linhas'])->firstWhere('tipo', TipoMovimentacaoDia::Estorno);
        $this->assertNotNull($estorno, 'A remocao aparece como Estorno.');
        $this->assertSame(30.00, $estorno['valor']);
        $this->assertSame(0.0, $timeline['resultado'], 'Venda e estorno se anulam no dia.');
    }
}
