<?php

declare(strict_types=1);

namespace Tests\Feature\Caixa;

use App\Enums\{StatusCaixa, StatusPagamento, TipoFormaPagamento, TipoLancamento};
use App\Exceptions\NegocioException;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Caixa\Services\CaixaService;
use App\Modules\Conta\Models\Lancamento;
use App\Modules\Conta\Services\ContaService;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\FormaPagamento\Services\FormaPagamentoService;
use App\Modules\Pagamento\Models\ParcelaPagamento;
use App\Modules\Tenant\Models\Empresa;
use Database\Factories\{PagamentoFactory, ParcelaPagamentoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regressao: baixa e estorno precisam cair na conta/caixa da EMPRESA da parcela,
 * no DIA de hoje (baixa) ou no caixa de ORIGEM (estorno), e nunca em um
 * caixa aberto qualquer que o escopo ambiente enxergue.
 *
 * Bug original: CaixaService::caixaAberto() = Caixa::where(status, Aberto)->first()
 * pegava o primeiro caixa aberto visivel (menor id), ignorando empresa e data.
 * Com o razao unificado (ADR-0010) a baixa em dinheiro grava um Lancamento na
 * conta-caixa da empresa, com caixa_id da sessao aberta do dia.
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

    private function formaDinheiroDa(int $redeId, int $empresaId): FormaPagamento
    {
        return FormaPagamento::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('tipo', TipoFormaPagamento::Dinheiro->value)
            ->firstOrFail();
    }

    public function test_baixa_cai_no_caixa_da_empresa_da_parcela(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $rede = $ctx['rede'];
        $emp1 = $ctx['empresa'];
        $usuario = $ctx['usuario'];

        // Segunda empresa na mesma rede, com suas proprias contas e formas.
        $emp2 = Empresa::create(['rede_id' => $rede->id, 'nome' => 'Empresa 2']);
        app(ContaService::class)->semearPadrao($rede->id, $emp2->id);
        app(FormaPagamentoService::class)->semearPadrao($rede->id, $emp2->id);

        // Admin enxerga as duas empresas (como o multi-select do header).
        session(['empresas_atuais' => [$emp1->id, $emp2->id]]);

        // Caixa da empresa 1 criado PRIMEIRO (menor id) — o ->first() antigo o pegaria.
        $this->caixaAberto($emp1->id, $rede->id, $usuario->id, today()->toDateString());
        $caixaEmp2 = $this->caixaAberto($emp2->id, $rede->id, $usuario->id, today()->toDateString());

        $parcela = $this->parcelaAPrazo($rede->id, $emp2->id, 75.00);

        // Defesa em profundidade que o PagamentoController faz na baixa.
        session(['empresa_criacao_atual' => $emp2->id]);
        $baixa = app(CaixaService::class)->darBaixaParcelaPagamento($parcela, 75.00, $this->formaDinheiroDa($rede->id, $emp2->id));
        session()->forget('empresa_criacao_atual');

        $lancamento = Lancamento::withoutGlobalScope('empresa')->where('baixa_pagamento_id', $baixa->id)->firstOrFail();

        $this->assertSame(
            $emp2->id,
            (int) $lancamento->empresa_id,
            'O crédito deveria cair na empresa 2 (da parcela), nao na empresa 1.'
        );
        $this->assertSame($caixaEmp2->id, (int) $lancamento->caixa_id, 'O lançamento deveria apontar para o caixa da empresa 2.');
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

        $baixa = app(CaixaService::class)->darBaixaParcelaPagamento($parcela, 50.00, $this->formaDinheiroDa($rede->id, $emp->id));

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

        app(CaixaService::class)->darBaixaParcelaPagamento($parcela, 50.00, $this->formaDinheiroDa($rede->id, $emp->id));
    }

    public function test_estorno_reverte_no_caixa_de_origem_pelo_valor_liquido(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $rede = $ctx['rede'];
        $emp = $ctx['empresa'];
        $usuario = $ctx['usuario'];

        $caixaHoje = $this->caixaAberto($emp->id, $rede->id, $usuario->id, today()->toDateString());

        $parcela = $this->parcelaAPrazo($rede->id, $emp->id, 75.00);

        // Baixa em dinheiro com R$10 de desconto: entra liquido R$65 (nao R$75 principal).
        $baixa = app(CaixaService::class)->darBaixaParcelaPagamento(
            $parcela,
            75.00,
            $this->formaDinheiroDa($rede->id, $emp->id),
            desconto: 10.00,
        );

        $entrada = Lancamento::where('baixa_pagamento_id', $baixa->id)
            ->where('tipo', TipoLancamento::Credito)
            ->firstOrFail();
        $this->assertSame(65.00, (float) $entrada->valor, 'A entrada deveria ser o liquido (75 - 10).');
        $this->assertSame($caixaHoje->id, (int) $entrada->caixa_id);

        // Estorna o titulo.
        app(CaixaService::class)->estornarPagamento($parcela->pagamento->fresh());

        $saida = Lancamento::where('baixa_pagamento_id', $baixa->id)
            ->where('tipo', TipoLancamento::Debito)
            ->firstOrFail();

        $this->assertSame('estorno', $saida->categoria);
        $this->assertSame($caixaHoje->id, (int) $saida->caixa_id, 'O estorno deveria sair do MESMO caixa em que entrou.');
        $this->assertSame(65.00, (float) $saida->valor, 'O estorno deveria devolver o liquido que entrou (65), nao o principal (75).');
        $this->assertSame(StatusPagamento::Estornado, $parcela->pagamento->fresh()->status);
    }
}
