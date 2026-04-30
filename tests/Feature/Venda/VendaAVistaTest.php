<?php

namespace Tests\Feature\Venda;

use App\Enums\CondicaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\StatusCaixa;
use App\Enums\StatusParcela;
use App\Enums\TipoMovimentoCaixa;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Caixa\Models\MovimentoCaixa;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Produto\Models\Produto;
use App\Modules\Venda\Services\VendaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre o fluxo de venda a vista de produto: criar venda -> criar
 * Pagamento + 1 parcela ja paga -> registrar MovimentoCaixa de entrada
 * no caixa aberto.
 *
 * Atalho consciente: chama VendaService diretamente em vez de POST
 * /vendas. O endpoint HTTP cobre validacoes de Request e ja e
 * exercitado em outros testes (LoginTest, RegistroTest); aqui o foco
 * e na cascata de regra de negocio — Venda -> Pagamento -> Parcela ->
 * Baixa -> MovimentoCaixa.
 */
class VendaAVistaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_pagamento_e_baixa_caixa(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $produto = Produto::create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Shampoo Profissional',
            'valor_venda' => 50.00,
            'valor_custo' => 25.00,
            'quantidade' => 10,
            'ativo' => true,
        ]);

        Caixa::create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
            'data' => today()->toDateString(),
            'saldo_abertura' => 100.00,
            'status' => StatusCaixa::Aberto,
        ]);

        app(VendaService::class)->criarVendaProduto(
            cliente_id: null,
            itens: [[
                'produto_id' => $produto->id,
                'quantidade' => 2,
                'valor_unitario' => 50.00,
                'desconto' => 0,
                'acrescimo' => 0,
            ]],
            condicao: CondicaoPagamento::AVista,
            mesReferencia: Carbon::now()->startOfMonth(),
            formaAvista: FormaPagamento::Dinheiro,
            numeroParcelas: null,
            primeiroVencimento: now(),
        );

        $pagamento = Pagamento::with('parcelas')->latest('id')->firstOrFail();

        $this->assertSame(100.00, (float) $pagamento->valor_total, 'Total da venda deveria ser 2 x R$ 50.');
        $this->assertCount(1, $pagamento->parcelas, 'Venda a vista deveria gerar 1 parcela.');

        $parcela = $pagamento->parcelas->first();
        $this->assertSame(StatusParcela::Pago, $parcela->status, 'Parcela unica de venda a vista deveria ficar paga.');
        $this->assertSame(100.00, (float) $parcela->valor_pago);

        $this->assertDatabaseHas('movimentos_caixa', [
            'tipo' => TipoMovimentoCaixa::Entrada->value,
            'valor' => 100.00,
        ]);

        $entradas = MovimentoCaixa::where('tipo', TipoMovimentoCaixa::Entrada)->sum('valor');
        $this->assertSame(100.00, (float) $entradas, 'Entrada de caixa deveria igualar o valor da parcela.');

        // Estoque foi baixado
        $this->assertSame(8, $produto->fresh()->quantidade, 'Estoque deveria ter sido decrementado em 2 unidades.');
    }
}
