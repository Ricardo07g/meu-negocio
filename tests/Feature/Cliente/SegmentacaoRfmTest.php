<?php

declare(strict_types=1);

namespace Tests\Feature\Cliente;

use App\Enums\{SegmentoRfm, StatusVendaEtapas, StatusVendaProduto};
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Cliente\Services\SegmentacaoRfmService;
use App\Modules\Venda\Models\{VendaEtapas, VendaProduto};
use Database\Factories\{ClienteFactory, ServicoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * A inteligencia do recurso mora aqui, em SQL — nao no modelo de IA. Por isso ela tem
 * teste proprio, sem provedor nenhum envolvido.
 */
class SegmentacaoRfmTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    private array $contexto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contexto = $this->criarRedeAutenticada();
    }

    private function cliente(string $nome): Cliente
    {
        return ClienteFactory::new()->create([
            'rede_id' => $this->contexto['rede']->id,
            'nome' => $nome,
        ]);
    }

    private function venderProduto(Cliente $cliente, float $valor, int $diasAtras, string $status = 'ativa'): VendaProduto
    {
        return VendaProduto::create([
            'rede_id' => $this->contexto['rede']->id,
            'empresa_id' => $this->contexto['empresa']->id,
            'cliente_id' => $cliente->id,
            'usuario_id' => $this->contexto['usuario']->id,
            'data' => now()->subDays($diasAtras)->toDateString(),
            'subtotal' => $valor,
            'desconto' => 0,
            'acrescimo' => 0,
            'valor_total' => $valor,
            'status' => $status,
        ]);
    }

    private function segmentoDe(array $resultado, string $nome): ?SegmentoRfm
    {
        return $resultado['clientes']->firstWhere('nome', $nome)['segmento'] ?? null;
    }

    public function test_cliente_frequente_e_recente_vira_campeao(): void
    {
        $cliente = $this->cliente('Maria Campea');
        foreach ([5, 20, 40, 70, 100, 130] as $dias) {
            $this->venderProduto($cliente, 200, $dias);
        }

        $resultado = app(SegmentacaoRfmService::class)->segmentar();

        $this->assertSame(SegmentoRfm::Campeao, $this->segmentoDe($resultado, 'Maria Campea'));
    }

    public function test_uma_unica_compra_recente_vira_novo_e_nao_campeao(): void
    {
        $cliente = $this->cliente('Joao Novo');
        $this->venderProduto($cliente, 5000, 3);

        $resultado = app(SegmentacaoRfmService::class)->segmentar();

        $this->assertSame(
            SegmentoRfm::Novo,
            $this->segmentoDe($resultado, 'Joao Novo'),
            'valor alto nao pode promover quem so comprou uma vez'
        );
    }

    public function test_quem_comprava_muito_e_sumiu_vira_em_risco(): void
    {
        $cliente = $this->cliente('Ana Sumindo');
        foreach ([200, 240, 280, 320] as $dias) {
            $this->venderProduto($cliente, 300, $dias);
        }

        $resultado = app(SegmentacaoRfmService::class)->segmentar();

        $this->assertSame(SegmentoRfm::EmRisco, $this->segmentoDe($resultado, 'Ana Sumindo'));
    }

    public function test_compra_unica_e_antiga_vira_sumido(): void
    {
        $cliente = $this->cliente('Carlos Sumido');
        $this->venderProduto($cliente, 100, 300);

        $resultado = app(SegmentacaoRfmService::class)->segmentar();

        $this->assertSame(SegmentoRfm::Sumido, $this->segmentoDe($resultado, 'Carlos Sumido'));
    }

    public function test_venda_cancelada_nao_conta(): void
    {
        $cliente = $this->cliente('Pedro Cancelado');
        $this->venderProduto($cliente, 900, 10, StatusVendaProduto::Cancelada->value);

        $resultado = app(SegmentacaoRfmService::class)->segmentar();

        $this->assertSame(0, $resultado['clientes_com_compra']);
        $this->assertSame(0.0, $resultado['receita_total']);
    }

    public function test_venda_de_servico_e_de_produto_somam_no_mesmo_cliente(): void
    {
        $cliente = $this->cliente('Bia Mista');
        $servico = ServicoFactory::new()->create(['rede_id' => $this->contexto['rede']->id]);

        $this->venderProduto($cliente, 100, 10);

        VendaEtapas::create([
            'rede_id' => $this->contexto['rede']->id,
            'empresa_id' => $this->contexto['empresa']->id,
            'cliente_id' => $cliente->id,
            'servico_id' => $servico->id,
            'atendente_id' => $this->contexto['usuario']->id,
            'data' => now()->subDays(5)->toDateString(),
            'valor_total' => 250,
            'desconto' => 0,
            'acrescimo' => 0,
            'qtd_etapas' => 1,
            'status' => StatusVendaEtapas::Concluido->value,
        ]);

        $resultado = app(SegmentacaoRfmService::class)->segmentar();
        $linha = $resultado['clientes']->firstWhere('nome', 'Bia Mista');

        $this->assertSame(2, $linha['compras'], 'as duas fontes de venda precisam somar');
        $this->assertSame(350.0, $linha['valor']);
    }

    public function test_clientes_sem_compra_no_periodo_sao_contados(): void
    {
        $comprador = $this->cliente('Compra');
        $this->cliente('Nunca Comprou');
        $this->cliente('Tambem Nao');
        $this->venderProduto($comprador, 100, 10);

        $resultado = app(SegmentacaoRfmService::class)->segmentar();

        $this->assertSame(3, $resultado['total_clientes']);
        $this->assertSame(1, $resultado['clientes_com_compra']);
        $this->assertSame(2, $resultado['clientes_sem_compra']);
    }

    public function test_base_vazia_nao_quebra_e_zera_os_numeros(): void
    {
        $resultado = app(SegmentacaoRfmService::class)->segmentar();

        $this->assertSame(0, $resultado['clientes_com_compra']);
        $this->assertSame(0.0, $resultado['ticket_medio']);
        $this->assertCount(6, $resultado['segmentos']);
        $this->assertTrue($resultado['clientes']->isEmpty());
    }

    /**
     * Regressao: as faixas de Valor sao fracionarias e viviam num mapa `corte => nota`.
     * Chave de array em PHP nao aceita float — `[0.8 => 3, 0.4 => 2]` virava `[0 => 2]`,
     * e todo mundo abaixo de 1.2x da media recebia nota 2, inclusive quem devia receber
     * 3 ou 1. Passou despercebido em teste e foi o PHPStan que apontou.
     */
    public function test_nota_de_valor_respeita_as_faixas_fracionarias(): void
    {
        foreach ([1000, 1000, 1000, 1000] as $i => $valor) {
            $this->venderProduto($this->cliente("Medio {$i}"), $valor, 10);
        }
        $this->venderProduto($this->cliente('Na Media'), 900, 10);
        $this->venderProduto($this->cliente('Bem Abaixo'), 300, 10);

        $clientes = app(SegmentacaoRfmService::class)->segmentar()['clientes'];

        // Media por cliente = 5200 / 6 = 866,67.
        $this->assertSame(
            3,
            $clientes->firstWhere('nome', 'Na Media')['m'],
            '900 / 866,67 = 1,04x a media => faixa 0.8, nota 3'
        );
        $this->assertSame(
            1,
            $clientes->firstWhere('nome', 'Bem Abaixo')['m'],
            '300 / 866,67 = 0,35x a media => abaixo de 0.4, nota 1'
        );
    }

    public function test_maior_gastador_recebe_nota_maxima_de_valor(): void
    {
        foreach ([100, 100, 100, 100] as $i => $valor) {
            $this->venderProduto($this->cliente("Pequeno {$i}"), $valor, 10);
        }
        $this->venderProduto($this->cliente('Baleia'), 5000, 10);

        $clientes = app(SegmentacaoRfmService::class)->segmentar()['clientes'];

        $this->assertSame(5, $clientes->firstWhere('nome', 'Baleia')['m']);
    }

    public function test_venda_de_outra_empresa_nao_entra_na_segmentacao(): void
    {
        $cliente = $this->cliente('Cliente Compartilhado');
        $outraEmpresa = $this->criarEmpresaExtra($this->contexto['rede']->id, 'Unidade 2');

        VendaProduto::create([
            'rede_id' => $this->contexto['rede']->id,
            'empresa_id' => $outraEmpresa->id,
            'cliente_id' => $cliente->id,
            'usuario_id' => $this->contexto['usuario']->id,
            'data' => now()->subDays(5)->toDateString(),
            'subtotal' => 999,
            'desconto' => 0,
            'acrescimo' => 0,
            'valor_total' => 999,
            'status' => StatusVendaProduto::Ativa->value,
        ]);

        // Contexto travado na empresa 1 (como o filtro de listagem faria).
        session(['empresa_contexto_atual' => $this->contexto['empresa']->id]);

        $resultado = app(SegmentacaoRfmService::class)->segmentar();

        $this->assertSame(0, $resultado['clientes_com_compra'], 'venda de outra unidade nao pode contar');
    }
}
