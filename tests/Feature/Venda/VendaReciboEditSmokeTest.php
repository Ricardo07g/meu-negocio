<?php

declare(strict_types=1);

namespace Tests\Feature\Venda;

use App\Enums\{StatusVendaEtapas, StatusVendaProduto};
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Tenant\Models\Empresa;
use App\Modules\Venda\Models\{VendaEtapas, VendaProduto};
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Factories\{AgendamentoFactory, ClienteFactory, PagamentoFactory, ParcelaPagamentoFactory, ProdutoFactory, ServicoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * Smoke dos GETs de leitura de Venda: recibo (PDF) e formularios de edicao
 * (editUnico / editEtapas / editProduto). Estes caminhos carregam relacoes
 * (pagamento, parcelas, agendamentos, itens) que sofreram o refactor
 * pacote->etapas — qualquer eager-load de relacao/coluna inexistente cairia
 * no tratarErro -> redirect-back com 'erro'. Por isso os asserts exigem 200
 * (recibo PDF / view de edicao) e ausencia de redirect de erro.
 *
 * As entidades sao criadas via Model::create + factories de Pagamento (em vez
 * de POST /vendas) para isolar estes caminhos do bug de store de etapas
 * (vendas_etapas.data NOT NULL, ver VendaStoreSmokeTest).
 */
class VendaReciboEditSmokeTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    private function montarContexto(): array
    {
        $contexto = $this->criarRedeAutenticada();

        return [
            'rede' => $contexto['rede'],
            'empresa' => $contexto['empresa'],
            'usuario' => $contexto['usuario'],
            'cliente' => ClienteFactory::new()->create(['rede_id' => $contexto['rede']->id]),
        ];
    }

    /** Cria agendamento "unico" (sem venda_etapas_id) + pagamento a prazo pendente. */
    private function criarVendaUnica(array $ctx): Agendamento
    {
        $servico = ServicoFactory::new()->avulso()->create([
            'rede_id' => $ctx['rede']->id,
            'valor' => 120.00,
        ]);

        $agendamento = AgendamentoFactory::new()->create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'cliente_id' => $ctx['cliente']->id,
            'servico_id' => $servico->id,
            'atendente_id' => $ctx['usuario']->id,
        ]);

        $pagamento = PagamentoFactory::new()->aPrazo()->create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'cliente_id' => $ctx['cliente']->id,
            'agendamento_id' => $agendamento->id,
            'valor_total' => 120.00,
        ]);
        ParcelaPagamentoFactory::new()->pendente()->create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'pagamento_id' => $pagamento->id,
            'valor' => 120.00,
        ]);

        return $agendamento->fresh();
    }

    private function criarVendaEtapas(array $ctx): VendaEtapas
    {
        $servico = ServicoFactory::new()->etapas(2)->create([
            'rede_id' => $ctx['rede']->id,
            'valor' => 100.00,
        ]);

        // Inclui `data` explicitamente (a coluna que o action de producao esquece).
        $etapas = VendaEtapas::create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'cliente_id' => $ctx['cliente']->id,
            'servico_id' => $servico->id,
            'atendente_id' => $ctx['usuario']->id,
            'data' => now()->format('Y-m-d'),
            'valor_total' => 200.00,
            'qtd_etapas' => 2,
            'status' => StatusVendaEtapas::Ativo,
        ]);

        AgendamentoFactory::new()->create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'cliente_id' => $ctx['cliente']->id,
            'servico_id' => $servico->id,
            'atendente_id' => $ctx['usuario']->id,
            'venda_etapas_id' => $etapas->id,
        ]);

        $pagamento = PagamentoFactory::new()->aPrazo()->create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'cliente_id' => $ctx['cliente']->id,
            'venda_etapas_id' => $etapas->id,
            'valor_total' => 200.00,
        ]);
        ParcelaPagamentoFactory::new()->pendente()->create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'pagamento_id' => $pagamento->id,
            'valor' => 200.00,
        ]);

        return $etapas->fresh();
    }

    private function criarVendaProduto(array $ctx): VendaProduto
    {
        $produto = ProdutoFactory::new()->create([
            'rede_id' => $ctx['rede']->id,
            'valor_venda' => 50.00,
        ]);

        $venda = VendaProduto::create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'cliente_id' => $ctx['cliente']->id,
            'usuario_id' => $ctx['usuario']->id,
            'data' => now()->format('Y-m-d'),
            'subtotal' => 100.00,
            'desconto' => 0,
            'acrescimo' => 0,
            'valor_total' => 100.00,
            'status' => StatusVendaProduto::Ativa,
        ]);

        $venda->itens()->create([
            'produto_id' => $produto->id,
            'descricao' => $produto->nome,
            'quantidade' => 2,
            'valor_unitario' => 50.00,
            'desconto' => 0,
            'acrescimo' => 0,
            'subtotal' => 100.00,
        ]);

        $pagamento = PagamentoFactory::new()->aPrazo()->create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'cliente_id' => $ctx['cliente']->id,
            'venda_produto_id' => $venda->id,
            'valor_total' => 100.00,
        ]);
        ParcelaPagamentoFactory::new()->pendente()->create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'pagamento_id' => $pagamento->id,
            'valor' => 100.00,
        ]);

        return $venda->fresh();
    }

    // ─── RECIBO (PDF) ──────────────────────────────────────────────────────

    public function test_recibo_unico_gera_pdf(): void
    {
        $ctx = $this->montarContexto();
        $agendamento = $this->criarVendaUnica($ctx);

        $resp = $this->get(route('vendas.recibo', ['tipo' => 'unico', 'id' => $agendamento->id]));

        $resp->assertOk();
        $this->assertSame('application/pdf', $resp->headers->get('content-type'));
    }

    public function test_recibo_etapas_gera_pdf(): void
    {
        $ctx = $this->montarContexto();
        $etapas = $this->criarVendaEtapas($ctx);

        $resp = $this->get(route('vendas.recibo', ['tipo' => 'etapas', 'id' => $etapas->id]));

        $resp->assertOk();
        $this->assertSame('application/pdf', $resp->headers->get('content-type'));
    }

    public function test_recibo_produto_gera_pdf(): void
    {
        $ctx = $this->montarContexto();
        $venda = $this->criarVendaProduto($ctx);

        $resp = $this->get(route('vendas.recibo', ['tipo' => 'produto', 'id' => $venda->id]));

        $resp->assertOk();
        $this->assertSame('application/pdf', $resp->headers->get('content-type'));
    }

    // ─── EDIT FORMS ────────────────────────────────────────────────────────

    public function test_edit_unico_abre_formulario(): void
    {
        $ctx = $this->montarContexto();
        $agendamento = $this->criarVendaUnica($ctx);

        $resp = $this->get(route('vendas.edit-unico', ['agendamento' => $agendamento->id]));

        $resp->assertOk();
        $resp->assertViewIs('venda::edit-unico');
    }

    public function test_edit_etapas_abre_formulario(): void
    {
        $ctx = $this->montarContexto();
        $etapas = $this->criarVendaEtapas($ctx);

        $resp = $this->get(route('vendas.edit-etapas', ['etapas' => $etapas->id]));

        $resp->assertOk();
        $resp->assertViewIs('venda::edit-etapas');
    }

    public function test_edit_produto_abre_formulario(): void
    {
        $ctx = $this->montarContexto();
        $venda = $this->criarVendaProduto($ctx);

        $resp = $this->get(route('vendas.edit-produto', ['vendaProduto' => $venda->id]));

        $resp->assertOk();
        $resp->assertViewIs('venda::edit-produto');
    }

    // ─── CREATE FORM ───────────────────────────────────────────────────────

    public function test_pagina_nova_venda_renderiza_container_do_card_de_cliente(): void
    {
        $this->criarRedeAutenticada();

        $resp = $this->get(route('vendas.create'));

        $resp->assertOk();
        $resp->assertViewIs('venda::create');
        // Container onde o JS injeta o card de dados do cliente selecionado.
        $resp->assertSee('id="clienteCard"', false);
        // Card de produtos reformulado: titulo, estado vazio e containers responsivos (tabela + cards).
        $resp->assertSee('Produtos da venda');
        $resp->assertSee('id="carrinhoVazioBlock"', false);
        $resp->assertSee('id="carrinhoTabelaWrap"', false);
        $resp->assertSee('id="carrinhoCards"', false);
        // Preview das etapas reformulado: colunas Inicio/Fim, resumo e aviso de duplicatas.
        $resp->assertSee('Preview das etapas');
        $resp->assertSee('>Início<', false);
        $resp->assertSee('>Fim<', false);
        $resp->assertSee('id="resumoEtapas"', false);
        $resp->assertSee('id="avisoEtapasDuplicadas"', false);
        // Seletor/indicador da empresa da venda (com 1 empresa: hidden name="empresa_id").
        $resp->assertSee('Empresa da venda');
        $resp->assertSee('name="empresa_id"', false);
    }

    /**
     * O comprovante deve refletir a empresa DA VENDA, nao a empresa-padrao de
     * quem imprime. Cria a venda numa empresa diferente da default do usuario e
     * captura os dados enviados a view do PDF (facade Pdf mockada) para conferir
     * que `empresa` e a do registro.
     */
    public function test_recibo_de_venda_usa_a_empresa_do_registro(): void
    {
        $ctx = $this->montarContexto();
        $empresaPadrao = $ctx['empresa'];
        $empresaOutra = Empresa::create(['rede_id' => $ctx['rede']->id, 'nome' => 'Filial Norte']);
        // Admin enxerga as duas empresas (senao o global scope esconderia a venda da Filial).
        session(['empresas_atuais' => [$empresaPadrao->id, $empresaOutra->id]]);

        $servico = ServicoFactory::new()->avulso()->create([
            'rede_id' => $ctx['rede']->id,
            'valor' => 120.00,
        ]);
        $agendamento = AgendamentoFactory::new()->create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $empresaOutra->id,
            'cliente_id' => $ctx['cliente']->id,
            'servico_id' => $servico->id,
            'atendente_id' => $ctx['usuario']->id,
        ]);

        $capturado = $this->capturarDadosDoRecibo();

        $this->get(route('vendas.recibo', ['unico', $agendamento->id]))->assertOk();

        $this->assertNotNull($capturado->dados);
        $this->assertSame($empresaOutra->id, $capturado->dados['empresa']->id);
        $this->assertSame('Filial Norte', $capturado->dados['empresa']->nome);
        $this->assertNotSame($empresaPadrao->id, $capturado->dados['empresa']->id);
    }

    /** Mocka a facade Pdf e devolve um objeto cujo ->dados recebe o array passado ao loadView. */
    private function capturarDadosDoRecibo(): object
    {
        $captura = new class
        {
            public ?array $dados = null;
        };

        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('stream')->andReturn(response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']));

        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function ($view, $dados) use ($captura, $pdf) {
            $captura->dados = $dados;

            return $pdf;
        });

        return $captura;
    }
}
