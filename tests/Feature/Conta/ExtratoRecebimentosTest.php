<?php

declare(strict_types=1);

namespace Tests\Feature\Conta;

use App\Enums\{FormatoExportacao, StatusExportacao, TipoConta};
use App\Modules\Conta\Jobs\GerarExportacaoExtrato;
use App\Modules\Conta\Models\{Conta, Exportacao};
use Database\Factories\{BaixaPagamentoFactory, ClienteFactory, PagamentoFactory, ParcelaPagamentoFactory};
use Database\Factories\LancamentoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Conta banco/carteira nao acumula `Lancamento` (ADR-0011): o extrato dela sao
 * os RECEBIMENTOS que cairam ali. Este teste cobre os dois pontos em que a tela
 * e o arquivo divergiam da verdade:
 *
 *  - a linha perdia o cliente quando a venda era cancelada (o titulo usa
 *    SoftDeletes e a baixa nao — sem `withTrashed` a relacao some);
 *  - a exportacao lia so lancamentos e devolvia uma planilha vazia, apesar de a
 *    mesma tela listar os recebimentos logo abaixo.
 */
class ExtratoRecebimentosTest extends TestCase
{
    use RefreshDatabase;

    private function contaBanco(array $contexto): Conta
    {
        return Conta::where('empresa_id', $contexto['empresa']->id)
            ->where('tipo', TipoConta::Banco)
            ->firstOrFail();
    }

    /**
     * Recebimento completo (cliente -> titulo -> parcela -> baixa) caindo na conta.
     */
    private function recebimento(array $contexto, Conta $conta, array $atributos = []): array
    {
        $base = [
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
        ];

        $cliente = ClienteFactory::new()->create(['rede_id' => $contexto['rede']->id]);
        $pagamento = PagamentoFactory::new()->create($base + ['cliente_id' => $cliente->id]);
        $parcela = ParcelaPagamentoFactory::new()->create($base + ['pagamento_id' => $pagamento->id]);

        $baixa = BaixaPagamentoFactory::new()->create($base + [
            'parcela_pagamento_id' => $parcela->id,
            'conta_id' => $conta->id,
            'data' => now(),
        ] + $atributos);

        return ['cliente' => $cliente, 'pagamento' => $pagamento, 'baixa' => $baixa];
    }

    public function test_extrato_de_conta_banco_mostra_o_cliente_de_venda_cancelada(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $conta = $this->contaBanco($contexto);

        $dados = $this->recebimento($contexto, $conta, ['estornado_em' => now()]);

        // Cancelar a venda apaga o titulo (SoftDeletes); a baixa fica.
        $dados['pagamento']->delete();

        $response = $this->get(route('contas.extrato', ['conta' => $conta, 'mes' => now()->format('Y-m')]));

        $response->assertOk();
        $response->assertSee($dados['cliente']->nome);
    }

    public function test_extrato_de_conta_banco_nao_soma_estorno_no_recebido(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $conta = $this->contaBanco($contexto);

        $this->recebimento($contexto, $conta, ['valor' => 30.00]);
        $this->recebimento($contexto, $conta, ['valor' => 45.00, 'estornado_em' => now()]);

        $response = $this->get(route('contas.extrato', ['conta' => $conta, 'mes' => now()->format('Y-m')]));

        $response->assertOk();
        $response->assertViewHas('recebidoLiquido', 30.00);
    }

    public function test_exportacao_de_conta_banco_traz_os_recebimentos(): void
    {
        Storage::fake('r2');

        $contexto = $this->criarRedeAutenticada();
        $conta = $this->contaBanco($contexto);

        $dados = $this->recebimento($contexto, $conta, ['valor' => 45.00, 'forma_pagamento_nome' => 'Cartão de Crédito']);
        $this->recebimento($contexto, $conta, ['valor' => 30.00]);

        $exportacao = Exportacao::create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'conta_id' => $conta->id,
            'usuario_id' => $contexto['usuario']->id,
            'formato' => FormatoExportacao::Csv,
            'periodo_inicio' => now()->startOfMonth()->toDateString(),
            'periodo_fim' => now()->endOfMonth()->toDateString(),
            'status' => StatusExportacao::Processando,
        ]);

        (new GerarExportacaoExtrato($exportacao->id))->handle();

        $exportacao->refresh();
        $this->assertSame(StatusExportacao::Pronto, $exportacao->status);

        $conteudo = Storage::disk('r2')->get((string) $exportacao->caminho);

        $this->assertStringContainsString('Cliente', (string) $conteudo, 'Cabecalho de recebimentos, nao o do razao.');
        $this->assertStringContainsString($dados['cliente']->nome, (string) $conteudo);
        $this->assertStringContainsString('Cartão de Crédito', (string) $conteudo);
        $this->assertStringContainsString('45,00', (string) $conteudo);
        $this->assertStringContainsString('30,00', (string) $conteudo);
    }

    public function test_exportacao_de_conta_caixa_continua_exportando_o_razao(): void
    {
        Storage::fake('r2');

        $contexto = $this->criarRedeAutenticada();
        $gaveta = Conta::where('empresa_id', $contexto['empresa']->id)
            ->where('eh_caixa_padrao', true)
            ->firstOrFail();

        LancamentoFactory::new()->credito()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'conta_id' => $gaveta->id,
            'valor' => 120.00,
            'descricao' => 'Venda no balcão',
        ]);

        $exportacao = Exportacao::create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'conta_id' => $gaveta->id,
            'usuario_id' => $contexto['usuario']->id,
            'formato' => FormatoExportacao::Csv,
            'periodo_inicio' => now()->startOfMonth()->toDateString(),
            'periodo_fim' => now()->endOfMonth()->toDateString(),
            'status' => StatusExportacao::Processando,
        ]);

        (new GerarExportacaoExtrato($exportacao->id))->handle();

        $exportacao->refresh();
        $conteudo = (string) Storage::disk('r2')->get((string) $exportacao->caminho);

        $this->assertStringContainsString('Categoria', $conteudo, 'Gaveta segue no cabecalho do razao.');
        $this->assertStringContainsString('Venda no balcão', $conteudo);
    }
}
