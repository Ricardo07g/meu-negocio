<?php

namespace Tests\Feature\Despesa;

use App\Enums\FormaPagamento;
use App\Modules\Despesa\Models\Despesa;
use Database\Factories\CaixaFactory;
use Database\Factories\DespesaFactory;
use Database\Factories\ParcelaDespesaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Garante a barreira de autorizacao do modulo Despesa:
 *  - Um usuario sem `despesa.editar` recebe 403 ao tentar dar baixa em
 *    parcela (espelho do FECH-010 do lado de Pagamento — receber e pagar
 *    sao permissoes distintas).
 *  - Um usuario sem `despesa.criar` recebe 403 no formulario de criacao.
 */
class PermissoesTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_sem_despesa_editar_nao_pode_dar_baixa(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // Papel Recepcao so com leitura de despesa (sem despesa.editar).
        $papel = Role::firstOrCreate(['name' => 'Recepcao', 'guard_name' => 'web']);
        $papel->syncPermissions(['despesa.ver']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $recepcao = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Recepcao');

        $despesa = DespesaFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'valor_total' => 100.00,
        ]);

        $parcela = ParcelaDespesaFactory::new()->pendente()->create([
            'despesa_id' => $despesa->id,
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'numero' => 1,
            'total' => 1,
            'valor' => 100.00,
        ]);

        CaixaFactory::new()->aberto()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
            'data' => today()->format('Y-m-d'),
        ]);

        $this->actingAs($recepcao);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $response = $this->post(route('parcelas-despesa.baixa', $parcela), [
            'valor' => 100.00,
            'forma_pagamento' => FormaPagamento::Dinheiro->value,
        ]);

        $response->assertForbidden();
    }

    public function test_usuario_sem_despesa_criar_nao_acessa_formulario(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $papel = Role::firstOrCreate(['name' => 'Recepcao', 'guard_name' => 'web']);
        $papel->syncPermissions(['despesa.ver']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $recepcao = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Recepcao');

        $this->actingAs($recepcao);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $response = $this->get(route('despesas.create'));

        // Controller usa $this->authorize('create', Despesa::class); sem a
        // permissao o TratamentoErros redireciona com mensagem de erro.
        $this->assertTrue(
            $response->isForbidden() || $response->isRedirect(),
            'Usuario sem despesa.criar nao deveria acessar o formulario.'
        );
        $this->assertTrue(
            $contexto['usuario']->can('despesa.criar'),
            'Sanidade: o Admin do contexto deveria poder criar despesa.'
        );
        $this->assertFalse($recepcao->can('despesa.criar'), 'Recepcao nao deveria ter despesa.criar.');
        $this->assertTrue(class_exists(Despesa::class));
    }
}
