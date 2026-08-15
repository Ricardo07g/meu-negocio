<?php

declare(strict_types=1);

namespace Tests\Feature\Agenda;

use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Tenant\Models\Empresa;
use Database\Factories\{AgendamentoFactory, ClienteFactory, ServicoFactory};
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

        $empB = $this->criarEmpresaExtra($rede->id, 'Empresa B');

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

    /**
     * O global scope protege a LEITURA; a validacao precisa proteger a ESCRITA.
     *
     * `exists:clientes,id` monta a propria query e ignora o scope de rede — dava
     * para amarrar um agendamento ao cliente de outra rede so trocando o id no
     * POST. `RegrasDeVinculo` fecha isso escopando o `exists` por `rede_id`.
     */
    public function test_nao_cria_agendamento_com_cliente_de_outra_rede(): void
    {
        $outra = $this->criarRede('outra');
        $clienteAlheio = ClienteFactory::new()->create(['rede_id' => $outra['rede']->id]);

        $contexto = $this->criarRedeAutenticada();
        $servico = ServicoFactory::new()->create(['rede_id' => $contexto['rede']->id]);

        $resp = $this->postJson(route('agenda.criar-rapido'), [
            'cliente_id' => $clienteAlheio->id,
            'servico_id' => $servico->id,
            'atendente_id' => $contexto['usuario']->id,
            'inicio' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $resp->assertStatus(422);
        $this->assertSame(
            0,
            Agendamento::withoutGlobalScopes()->count(),
            'Cliente de outra rede nao pode virar agendamento aqui.'
        );
    }

    public function test_nao_cria_agendamento_com_servico_de_outra_rede(): void
    {
        $outra = $this->criarRede('outra');
        $servicoAlheio = ServicoFactory::new()->create(['rede_id' => $outra['rede']->id]);

        $contexto = $this->criarRedeAutenticada();
        $cliente = ClienteFactory::new()->create(['rede_id' => $contexto['rede']->id]);

        $resp = $this->postJson(route('agenda.criar-rapido'), [
            'cliente_id' => $cliente->id,
            'servico_id' => $servicoAlheio->id,
            'atendente_id' => $contexto['usuario']->id,
            'inicio' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $resp->assertStatus(422);
        $this->assertSame(0, Agendamento::withoutGlobalScopes()->count());
    }

    public function test_nao_cria_agendamento_com_atendente_de_outra_rede(): void
    {
        $outra = $this->criarRede('outra');

        $contexto = $this->criarRedeAutenticada();
        $cliente = ClienteFactory::new()->create(['rede_id' => $contexto['rede']->id]);
        $servico = ServicoFactory::new()->create(['rede_id' => $contexto['rede']->id]);

        $resp = $this->postJson(route('agenda.criar-rapido'), [
            'cliente_id' => $cliente->id,
            'servico_id' => $servico->id,
            'atendente_id' => $outra['usuario']->id,
            'inicio' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $resp->assertStatus(422);
        $this->assertSame(0, Agendamento::withoutGlobalScopes()->count());
    }
}
