<?php

declare(strict_types=1);

namespace Tests\Feature\Agenda;

use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Tenant\Models\Empresa;
use Database\Factories\AgendamentoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garante o isolamento transacional do Agendamento:
 *  - rede A nao ve agendamentos da rede B (RedeTrait global scope);
 *  - empresa A nao ve agendamentos da empresa B dentro da mesma rede,
 *    quando ha contexto de empresa vigente (EmpresaTrait global scope).
 *
 * O Agendamento usa BaseModel (RedeTrait) + EmpresaTrait.
 */
class IsolamentoAgendamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_rede_a_nao_ve_agendamentos_rede_b(): void
    {
        $redeA = $this->criarRede('A');
        $redeB = $this->criarRede('B');

        $agA = AgendamentoFactory::new()->create([
            'rede_id' => $redeA['rede']->id,
            'empresa_id' => $redeA['empresa']->id,
            'atendente_id' => $redeA['usuario']->id,
        ]);

        $agB = AgendamentoFactory::new()->create([
            'rede_id' => $redeB['rede']->id,
            'empresa_id' => $redeB['empresa']->id,
            'atendente_id' => $redeB['usuario']->id,
        ]);

        // --- Logado como admin da Rede A ---
        $this->actingAs($redeA['usuario']);
        session(['empresas_atuais' => [$redeA['empresa']->id]]);

        $idsA = Agendamento::query()->pluck('id')->all();
        $this->assertSame([$agA->id], $idsA, 'Admin da Rede A so deveria ver agendamentos da propria rede.');
        $this->assertNull(Agendamento::find($agB->id), 'Agendamento de outra rede deveria ser invisivel via find().');

        // --- Logado como admin da Rede B ---
        $this->actingAs($redeB['usuario']);
        session(['empresas_atuais' => [$redeB['empresa']->id]]);

        $idsB = Agendamento::query()->pluck('id')->all();
        $this->assertSame([$agB->id], $idsB, 'Admin da Rede B so deveria ver agendamentos da propria rede.');
        $this->assertNull(Agendamento::find($agA->id), 'Agendamento de outra rede deveria ser invisivel via find().');
    }

    public function test_empresa_a_nao_ve_agendamento_empresa_b_com_contexto(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empA = $contexto['empresa'];

        $empB = Empresa::create([
            'rede_id' => $rede->id,
            'nome' => 'Empresa B',
        ]);

        session(['empresas_atuais' => [$empA->id, $empB->id]]);

        $agA = AgendamentoFactory::new()->create([
            'rede_id' => $rede->id,
            'empresa_id' => $empA->id,
            'atendente_id' => $contexto['usuario']->id,
        ]);

        $agB = AgendamentoFactory::new()->create([
            'rede_id' => $rede->id,
            'empresa_id' => $empB->id,
            'atendente_id' => $contexto['usuario']->id,
        ]);

        // Sem contexto especifico (empresas_atuais = ambas), enxerga as duas.
        $idsAmbas = Agendamento::query()->pluck('id')->sort()->values()->all();
        $this->assertSame(
            collect([$agA->id, $agB->id])->sort()->values()->all(),
            $idsAmbas,
            'Sem contexto, o universo de empresas atuais inclui A e B.'
        );

        // Com contexto = empresa A, o EmpresaTrait filtra apenas A.
        session(['empresa_contexto_atual' => $empA->id]);
        $idsContextoA = Agendamento::query()->pluck('id')->all();
        $this->assertSame([$agA->id], $idsContextoA, 'Com contexto=A, agendamento de B deve ficar fora do scope.');
        $this->assertNull(Agendamento::find($agB->id), 'Agendamento da empresa B nao deve ser carregavel com contexto=A.');
    }
}
