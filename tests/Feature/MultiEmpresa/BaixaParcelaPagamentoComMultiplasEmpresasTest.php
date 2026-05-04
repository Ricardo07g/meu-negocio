<?php

namespace Tests\Feature\MultiEmpresa;

use App\Enums\CondicaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\FormaRecebimentoPrazo;
use App\Enums\StatusCaixa;
use App\Modules\Caixa\Models\BaixaPagamento;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Produto\Models\Produto;
use App\Modules\Tenant\Models\Empresa;
use App\Modules\Venda\Services\VendaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garante que `BaixaPagamento` herde a `empresa_id` da parcela quando o
 * usuario tem 2+ empresas selecionadas no header.
 *
 * Sem o fix em PagamentoController::baixaParcela (set/forget de
 * `empresa_criacao_atual`), o EmpresaTrait deixaria empresa_id null e o
 * INSERT estouraria por NOT NULL constraint.
 */
class BaixaParcelaPagamentoComMultiplasEmpresasTest extends TestCase
{
    use RefreshDatabase;

    public function test_baixa_parcela_usa_empresa_da_parcela_com_2_empresas_selecionadas(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empA = $contexto['empresa'];
        $usuario = $contexto['usuario'];

        $empB = Empresa::create([
            'rede_id' => $rede->id,
            'nome' => 'Empresa B',
        ]);

        $produto = Produto::create([
            'rede_id' => $rede->id,
            'nome' => 'Produto Teste',
            'valor_venda' => 100.00,
            'valor_custo' => 50.00,
            'quantidade' => 10,
            'ativo' => true,
        ]);

        // Cria venda na empresa A com session=[empA] (1 empresa, trait auto-atribui).
        session(['empresas_atuais' => [$empA->id]]);
        Caixa::create([
            'rede_id' => $rede->id,
            'empresa_id' => $empA->id,
            'usuario_id' => $usuario->id,
            'data' => today()->toDateString(),
            'saldo_abertura' => 0,
            'status' => StatusCaixa::Aberto,
        ]);
        app(VendaService::class)->criarVendaProduto(
            cliente_id: null,
            itens: [[
                'produto_id' => $produto->id,
                'quantidade' => 1,
                'valor_unitario' => 100.00,
                'desconto' => 0,
                'acrescimo' => 0,
            ]],
            condicao: CondicaoPagamento::APrazo,
            mesReferencia: Carbon::now()->startOfMonth(),
            formaAvista: FormaPagamento::Pix,
            numeroParcelas: 2,
            primeiroVencimento: Carbon::now()->addMonth()->startOfDay(),
            formaRecebimentoPrazo: FormaRecebimentoPrazo::Carne,
        );

        $pagamento = Pagamento::with('parcelas')->latest('id')->firstOrFail();
        $parcela = $pagamento->parcelas->first();
        $this->assertSame($empA->id, $parcela->empresa_id, 'Parcela deveria ter sido criada na empresa A.');

        // Usuario amplia a selecao para 2 empresas no header. Sem o fix do
        // controller, o INSERT de BaixaPagamento explodiria por empresa_id NOT NULL.
        session(['empresas_atuais' => [$empA->id, $empB->id]]);

        $resp = $this->post(route('parcelas-pagamento.baixa', $parcela), [
            'valor' => (float) $parcela->valor,
            'forma_pagamento' => FormaPagamento::Pix->value,
            'observacao' => 'baixa via teste',
        ]);

        $resp->assertRedirect(route('pagamentos.index'));

        $baixa = BaixaPagamento::query()
            ->withoutGlobalScopes()
            ->where('parcela_pagamento_id', $parcela->id)
            ->first();

        $this->assertNotNull($baixa, 'BaixaPagamento deveria ter sido criada.');
        $this->assertSame($empA->id, $baixa->empresa_id, 'BaixaPagamento deve herdar a empresa da parcela origem.');
        $this->assertSame($rede->id, $baixa->rede_id, 'BaixaPagamento deve manter a rede da parcela origem.');

        // Pos-baixa, a session nao pode reter o override (vazamento de contexto).
        $this->assertNull(session('empresa_criacao_atual'), 'empresa_criacao_atual deveria ter sido limpa no finally.');
    }
}
