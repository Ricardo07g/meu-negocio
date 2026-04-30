<?php

namespace Tests\Feature;

use App\Enums\CondicaoPagamento;
use App\Enums\StatusDespesa;
use App\Enums\StatusVendaProduto;
use App\Enums\TipoMovimentoEstoque;
use App\Modules\Despesa\Models\Despesa;
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Produto\Models\Produto;
use App\Modules\Venda\Models\VendaProduto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Smoke test do Spatie ActivityLog (FECH-013): garante que ao criar
 * um registro nos models criticos transacionais, a tabela activity_log
 * recebe entrada. Sem esta verificacao a trait pode estar aplicada mas
 * desabilitada por config (`activitylog.enabled = false`) sem ninguem
 * notar.
 */
class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_criticos_geram_log_de_atividade(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $produto = Produto::create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Item para auditoria',
            'valor_venda' => 30.00,
            'valor_custo' => 15.00,
            'quantidade' => 5,
            'ativo' => true,
        ]);

        // 1) Despesa
        $logsAntes = Activity::count();
        $despesa = Despesa::create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'nome' => 'Conta de luz',
            'valor_total' => 150.00,
            'condicao_pagamento' => CondicaoPagamento::AVista,
            'mes_referencia' => now()->startOfMonth(),
            'data_emissao' => now()->toDateString(),
            'status' => StatusDespesa::Pendente,
        ]);
        $this->assertGreaterThan($logsAntes, Activity::count(), 'Criar Despesa deveria gerar log.');
        $this->assertTrue(
            Activity::where('subject_type', Despesa::class)
                ->where('subject_id', $despesa->id)
                ->exists(),
            'Activity log deveria ter entrada apontando para a Despesa criada.'
        );

        // 2) VendaProduto
        $venda = VendaProduto::create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
            'data' => now()->toDateString(),
            'subtotal' => 30.00,
            'valor_total' => 30.00,
            'status' => StatusVendaProduto::Ativa,
        ]);
        $this->assertTrue(
            Activity::where('subject_type', VendaProduto::class)
                ->where('subject_id', $venda->id)
                ->exists(),
            'Activity log deveria ter entrada apontando para a VendaProduto criada.'
        );

        // 3) MovimentoEstoque
        $movimento = MovimentoEstoque::create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'produto_id' => $produto->id,
            'tipo' => TipoMovimentoEstoque::Entrada,
            'quantidade' => 3,
        ]);
        $this->assertTrue(
            Activity::where('subject_type', MovimentoEstoque::class)
                ->where('subject_id', $movimento->id)
                ->exists(),
            'Activity log deveria ter entrada apontando para o MovimentoEstoque criado.'
        );
    }
}
