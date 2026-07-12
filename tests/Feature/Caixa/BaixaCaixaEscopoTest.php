<?php

declare(strict_types=1);

namespace Tests\Feature\Caixa;

use App\Enums\{FormaPagamento, StatusCaixa, StatusPagamento, TipoMovimentoCaixa};
use App\Exceptions\NegocioException;
use App\Modules\Caixa\Models\{Caixa, MovimentoCaixa};
use App\Modules\Caixa\Services\CaixaService;
use App\Modules\Pagamento\Models\ParcelaPagamento;
use App\Modules\Tenant\Models\Empresa;
use Database\Factories\{PagamentoFactory, ParcelaPagamentoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regressao: baixa e estorno precisam cair no caixa da EMPRESA da parcela,
 * no DIA de hoje (baixa) ou no caixa de ORIGEM (estorno), e nunca em um
 * caixa aberto qualquer que o escopo ambiente enxergue.
 *
 * Bug original: CaixaService::caixaAberto() = Caixa::where(status, Aberto)->first()
 * pegava o primeiro caixa aberto visivel (menor id), ignorando empresa e data.
 * Com Admin enxergando varias empresas (empresas_atuais) e varios caixas abertos,
 * o recebimento caia no caixa da empresa/dia errados.
 */
class BaixaCaixaEscopoTest extends TestCase
{
    use RefreshDatabase;

    private function caixaAberto(int $empresaId, int $redeId, int $usuarioId, string $data): Caixa
    {
        return Caixa::create([
            'rede_id' => $redeId,
            'empresa_id' => $empresaId,
            'usuario_id' => $usuarioId,
            'data' => $data,
            'saldo_abertura' => 0,
            'status' => StatusCaixa::Aberto,
        ]);
    }

    private function parcelaAPrazo(int $redeId, int $empresaId, float $valor): ParcelaPagamento
    {
        $pagamento = PagamentoFactory::new()->aPrazo()->create([
            'rede_id' => $redeId,
            'empresa_id' => $empresaId,
            'valor_total' => $valor * 2,
        ]);

        return ParcelaPagamentoFactory::new()->pendente()->create([
            'pagamento_id' => $pagamento->id,
            'rede_id' => $redeId,
            'empresa_id' => $empresaId,
            'numero' => 1,
            'total' => 2,
            'valor' => $valor,
            'data_vencimento' => today()->addMonth()->toDateString(),
        ]);
    }

    public function test_baixa_cai_no_caixa_da_empresa_da_parcela(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $rede = $ctx['rede'];
        $emp1 = $ctx['empresa'];
        $usuario = $ctx['usuario'];

        $emp2 = Empresa::create(['rede_id' => $rede->id, 'nome' => 'Empresa 2']);

        // Admin enxerga as duas empresas (como o multi-select do header).
        session(['empresas_atuais' => [$emp1->id, $emp2->id]]);

        // Caixa da empresa 1 criado PRIMEIRO (menor id) — o ->first() antigo o pegaria.
        $this->caixaAberto($emp1->id, $rede->id, $usuario->id, today()->toDateString());
        $caixaEmp2 = $this->caixaAberto($emp2->id, $rede->id, $usuario->id, today()->toDateString());

        $parcela = $this->parcelaAPrazo($rede->id, $emp2->id, 75.00);

        // Defesa em profundidade que o PagamentoController faz na baixa.
        session(['empresa_criacao_atual' => $emp2->id]);
        $baixa = app(CaixaService::class)->darBaixaParcelaPagamento($parcela, 75.00, FormaPagamento::Pix);
        session()->forget('empresa_criacao_atual');

        $movimento = MovimentoCaixa::where('baixa_pagamento_id', $baixa->id)->firstOrFail();
        $caixaDoMovimento = Caixa::withoutGlobalScope('empresa')->findOrFail($movimento->caixa_id);

        $this->assertSame(
            $emp2->id,
            $caixaDoMovimento->empresa_id,
            'A entrada deveria cair no caixa da empresa 2 (da parcela), nao no da empresa 1.'
        );
        $this->assertSame($caixaEmp2->id, $movimento->caixa_id);
        $this->assertSame($emp2->id, (int) $baixa->empresa_id);
        $this->assertSame($caixaEmp2->id, (int) $baixa->caixa_id, 'A baixa nao deveria apontar para o caixa de outra empresa.');
    }

    public function test_baixa_usa_o_caixa_de_hoje_e_nao_um_aberto_de_dia_anterior(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $rede = $ctx['rede'];
        $emp = $ctx['empresa'];
        $usuario = $ctx['usuario'];

        // Caixa de ontem deixado aberto (criado primeiro, menor id) + caixa de hoje.
        $this->caixaAberto($emp->id, $rede->id, $usuario->id, today()->subDay()->toDateString());
        $caixaHoje = $this->caixaAberto($emp->id, $rede->id, $usuario->id, today()->toDateString());

        $parcela = $this->parcelaAPrazo($rede->id, $emp->id, 50.00);

        $baixa = app(CaixaService::class)->darBaixaParcelaPagamento($parcela, 50.00, FormaPagamento::Dinheiro);

        $this->assertSame($caixaHoje->id, (int) $baixa->caixa_id, 'A baixa de hoje deveria cair no caixa de hoje, nao no de ontem.');
    }

    public function test_baixa_bloqueia_quando_nao_ha_caixa_de_hoje_aberto(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $rede = $ctx['rede'];
        $emp = $ctx['empresa'];
        $usuario = $ctx['usuario'];

        // So existe caixa aberto de ONTEM — nao serve para uma baixa de hoje.
        $this->caixaAberto($emp->id, $rede->id, $usuario->id, today()->subDay()->toDateString());

        $parcela = $this->parcelaAPrazo($rede->id, $emp->id, 50.00);

        $this->expectException(NegocioException::class);

        app(CaixaService::class)->darBaixaParcelaPagamento($parcela, 50.00, FormaPagamento::Dinheiro);
    }

    public function test_estorno_reverte_no_caixa_de_origem_pelo_valor_liquido(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $rede = $ctx['rede'];
        $emp = $ctx['empresa'];
        $usuario = $ctx['usuario'];

        $caixaHoje = $this->caixaAberto($emp->id, $rede->id, $usuario->id, today()->toDateString());

        $parcela = $this->parcelaAPrazo($rede->id, $emp->id, 75.00);

        // Baixa com R$10 de desconto: entra liquido R$65 (nao R$75 principal).
        $baixa = app(CaixaService::class)->darBaixaParcelaPagamento(
            $parcela,
            75.00,
            FormaPagamento::Pix,
            desconto: 10.00,
        );

        $entrada = MovimentoCaixa::where('baixa_pagamento_id', $baixa->id)
            ->where('tipo', TipoMovimentoCaixa::Entrada)
            ->firstOrFail();
        $this->assertSame(65.00, (float) $entrada->valor, 'A entrada deveria ser o liquido (75 - 10).');

        // Estorna o titulo.
        app(CaixaService::class)->estornarPagamento($parcela->pagamento->fresh());

        $saida = MovimentoCaixa::where('baixa_pagamento_id', $baixa->id)
            ->where('tipo', TipoMovimentoCaixa::Saida)
            ->firstOrFail();

        $this->assertSame($caixaHoje->id, (int) $saida->caixa_id, 'O estorno deveria sair do MESMO caixa em que entrou.');
        $this->assertSame(65.00, (float) $saida->valor, 'O estorno deveria devolver o liquido que entrou (65), nao o principal (75).');
        $this->assertSame(StatusPagamento::Estornado, $parcela->pagamento->fresh()->status);
    }
}
