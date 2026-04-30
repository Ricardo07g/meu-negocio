<?php

namespace Tests\Feature\Pagamento;

use App\Enums\CondicaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\FormaRecebimentoPrazo;
use App\Enums\StatusCaixa;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Produto\Models\Produto;
use App\Modules\Venda\Services\VendaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Garante que um usuario sem `pagamento.editar` recebe 403 ao tentar
 * dar baixa em uma parcela. Cobre o item FECH-010 (autorizacao
 * padronizada via Request) e protege contra regressao de papel
 * Profissional ganhar acesso financeiro silenciosamente.
 */
class PermissoesTest extends TestCase
{
    use RefreshDatabase;

    public function test_profissional_nao_pode_dar_baixa(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // Cria papel "Profissional" SEM pagamento.editar (o seeder so cria
        // Admin master; demais perfis sao definidos pela UI). Aqui montamos
        // um cenario minimo: papel existe mas sem a permissao critica.
        $papel = Role::firstOrCreate(['name' => 'Profissional', 'guard_name' => 'web']);
        $papel->syncPermissions(['pagamento.ver']); // permissoes apenas de leitura

        $profissional = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Profissional');

        // Setup: precisa de uma parcela para tentar baixar.
        $produto = Produto::create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Item teste',
            'valor_venda' => 100.00,
            'valor_custo' => 50.00,
            'quantidade' => 5,
            'ativo' => true,
        ]);

        Caixa::create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
            'data' => today()->toDateString(),
            'saldo_abertura' => 0,
            'status' => StatusCaixa::Aberto,
        ]);

        // Admin cria a venda a prazo (uma parcela pendente).
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

        // Re-loga como Profissional sem `pagamento.editar`.
        $this->actingAs($profissional);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $response = $this->post(route('parcelas-pagamento.baixa', $parcela), [
            'valor' => $parcela->valor,
            'forma_pagamento' => FormaPagamento::Dinheiro->value,
        ]);

        $response->assertForbidden();
    }
}
