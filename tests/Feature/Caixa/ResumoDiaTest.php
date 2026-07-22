<?php

declare(strict_types=1);

namespace Tests\Feature\Caixa;

use App\Modules\Caixa\Models\BaixaPagamento;
use App\Modules\Caixa\Services\ResumoDiaService;
use App\Modules\Tenant\Models\{Empresa, Rede};
use Database\Factories\{PagamentoFactory, ParcelaPagamentoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panorama do dia por forma (no Caixa Diario): pauta-se em QUANDO O CLIENTE PAGOU
 * (a baixa), soma por forma, neta os estornos do dia (baixa marcada `estornado_em`)
 * e isola por empresa. Eixo disjunto do saldo da gaveta (ADR-0011).
 */
class ResumoDiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_recebimentos_somam_por_forma_pela_data_do_pagamento(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $hoje = today()->toDateString();
        $ontem = today()->subDay()->toDateString();

        $this->criarBaixa($ctx['rede'], $ctx['empresa'], 'Dinheiro', 100, $hoje);
        $this->criarBaixa($ctx['rede'], $ctx['empresa'], 'Dinheiro', 50, $hoje);
        $this->criarBaixa($ctx['rede'], $ctx['empresa'], 'Cartão de Débito', 200, $hoje);
        $this->criarBaixa($ctx['rede'], $ctx['empresa'], 'Dinheiro', 999, $ontem); // outro dia

        $resumo = app(ResumoDiaService::class)->porForma($hoje);

        $dinheiro = collect($resumo['linhas'])->firstWhere('forma', 'Dinheiro');
        $debito = collect($resumo['linhas'])->firstWhere('forma', 'Cartão de Débito');

        $this->assertSame(150.0, $dinheiro['recebido'], 'Dinheiro do dia = 100 + 50.');
        $this->assertSame(2, $dinheiro['qtd']);
        $this->assertSame(200.0, $debito['recebido']);
        $this->assertSame(350.0, $resumo['totalRecebido'], 'Ontem (999) nao entra no dia de hoje.');
        $this->assertSame(350.0, $resumo['liquido']);
        $this->assertSame(0.0, $resumo['totalEstornado']);
    }

    public function test_estorno_no_dia_neta_o_liquido(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $hoje = today()->toDateString();

        $baixa = $this->criarBaixa($ctx['rede'], $ctx['empresa'], 'Dinheiro', 100, $hoje);
        $baixa->update(['estornado_em' => now()]);

        $resumo = app(ResumoDiaService::class)->porForma($hoje);
        $dinheiro = collect($resumo['linhas'])->firstWhere('forma', 'Dinheiro');

        $this->assertSame(100.0, $dinheiro['recebido']);
        $this->assertSame(100.0, $dinheiro['estornado']);
        $this->assertSame(0.0, $dinheiro['liquido'], 'Recebido e estornado no mesmo dia netam a zero.');
        $this->assertSame(0.0, $resumo['liquido']);
    }

    public function test_estorno_de_cartao_neta_pelo_bruto_da_baixa(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $hoje = today()->toDateString();

        // Bruto = valor + multa + juros - desconto; a baixa e valuada pelo bruto ao netar.
        $baixa = $this->criarBaixa($ctx['rede'], $ctx['empresa'], 'Cartão de Débito', 200, $hoje);
        $baixa->update(['estornado_em' => now()]);

        $resumo = app(ResumoDiaService::class)->porForma($hoje);
        $debito = collect($resumo['linhas'])->firstWhere('forma', 'Cartão de Débito');

        $this->assertSame(200.0, $debito['recebido']);
        $this->assertSame(200.0, $debito['estornado'], 'Neta pelo bruto da baixa.');
        $this->assertSame(0.0, $debito['liquido']);
    }

    public function test_resumo_isola_por_empresa(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $hoje = today()->toDateString();

        $this->criarBaixa($ctx['rede'], $ctx['empresa'], 'Dinheiro', 100, $hoje);

        // Segunda empresa na MESMA rede, com baixa propria — nao deve entrar no resumo da empresa em contexto.
        $empB = Empresa::create(['rede_id' => $ctx['rede']->id, 'nome' => 'Filial B']);
        $this->criarBaixa($ctx['rede'], $empB, 'Dinheiro', 500, $hoje);

        $resumo = app(ResumoDiaService::class)->porForma($hoje);

        $this->assertSame(100.0, $resumo['totalRecebido'], 'Baixa da Filial B (500) nao entra no contexto da empresa A.');
    }

    public function test_tela_do_caixa_renderiza_o_card(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $this->criarBaixa($ctx['rede'], $ctx['empresa'], 'Dinheiro', 100, today()->toDateString());

        $this->get(route('caixas.index'))
            ->assertOk()
            ->assertSee('Recebimentos do dia por forma')
            ->assertSee('Dinheiro');
    }

    private function criarBaixa(Rede $rede, Empresa $empresa, string $forma, float $valor, string $data): BaixaPagamento
    {
        $pagamento = PagamentoFactory::new()->create([
            'rede_id' => $rede->id,
            'empresa_id' => $empresa->id,
            'valor_total' => $valor,
        ]);

        $parcela = ParcelaPagamentoFactory::new()->create([
            'pagamento_id' => $pagamento->id,
            'rede_id' => $rede->id,
            'empresa_id' => $empresa->id,
            'valor' => $valor,
        ]);

        return BaixaPagamento::create([
            'rede_id' => $rede->id,
            'empresa_id' => $empresa->id,
            'parcela_pagamento_id' => $parcela->id,
            'valor' => $valor,
            'multa' => 0,
            'juros' => 0,
            'desconto' => 0,
            'forma_pagamento_nome' => $forma,
            'data' => $data,
        ]);
    }
}
