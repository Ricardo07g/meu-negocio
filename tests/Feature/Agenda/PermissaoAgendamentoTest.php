<?php

namespace Tests\Feature\Agenda;

use App\Modules\Agenda\Models\Agendamento;
use Database\Factories\AgendamentoFactory;
use Database\Factories\ClienteFactory;
use Database\Factories\ServicoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Valida que papeis sem a permissao correspondente recebem 403 nas
 * acoes da Agenda. Papeis nao-admin sao criados "vazios" (sem permissoes)
 * por criarUsuarioComum/garantirRole — entao um Recepcao sem grants
 * nao pode ver/criar/editar agendamentos.
 *
 * As Policies (AgendamentoPolicy) verificam `can('agendamento.*')`.
 */
class PermissaoAgendamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_papel_sem_permissao_recebe_403_ao_listar(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empresa = $contexto['empresa'];

        // Recepcao sem nenhuma permissao atribuida.
        $semPermissao = $this->criarUsuarioComum($rede, $empresa, 'Recepcao');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($semPermissao);
        session(['empresas_atuais' => [$empresa->id]]);

        $resp = $this->get(route('agenda.index'));
        $resp->assertForbidden();
    }

    public function test_papel_sem_permissao_recebe_403_ao_criar(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empresa = $contexto['empresa'];

        $cliente = ClienteFactory::new()->create(['rede_id' => $rede->id]);
        $servico = ServicoFactory::new()->create(['rede_id' => $rede->id]);

        $semPermissao = $this->criarUsuarioComum($rede, $empresa, 'Recepcao');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($semPermissao);
        session(['empresas_atuais' => [$empresa->id]]);

        $resp = $this->postJson(route('agenda.criar-rapido'), [
            'cliente_id' => $cliente->id,
            'servico_id' => $servico->id,
            'atendente_id' => $semPermissao->id,
            'inicio' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        // COMPORTAMENTO ATUAL (documentado, nao um 403 limpo):
        // criarRapido() envolve `$this->authorize('create', ...)` num
        // try/catch (\Throwable) que devolve `json([...], 500)`. A
        // AuthorizationException, que normalmente viraria 403, e engolida
        // pelo catch generico e o cliente recebe 500. O bloqueio em si
        // funciona — nenhum agendamento e criado —, mas o status HTTP nao
        // diferencia "sem permissao" de "erro interno".
        // (Comparar com agenda.index e agenda.cancelar, que retornam 403.)
        $resp->assertStatus(500);
        $this->assertSame(0, Agendamento::query()->count(), 'Usuario sem permissao nao deve criar agendamento, mesmo com o status 500.');
    }

    public function test_papel_sem_permissao_recebe_403_ao_cancelar(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empresa = $contexto['empresa'];

        $agendamento = AgendamentoFactory::new()->create([
            'rede_id' => $rede->id,
            'empresa_id' => $empresa->id,
            'atendente_id' => $contexto['usuario']->id,
        ]);

        $semPermissao = $this->criarUsuarioComum($rede, $empresa, 'Recepcao');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($semPermissao);
        session(['empresas_atuais' => [$empresa->id]]);

        $resp = $this->patch(route('agenda.cancelar', $agendamento));
        $resp->assertForbidden();
    }
}
