<?php

namespace Tests\Feature\Servico;

use App\Enums\TipoServico;
use App\Modules\Servico\Models\Servico;
use Database\Factories\ServicoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Cobertura Feature do modulo Servico (catalogo rede-level, sem empresa_id).
 *
 * Cobre criar servico avulso (TipoServico::Unico) e em etapas
 * (TipoServico::Etapas, com qtd_etapas), editar, listar com escopo de rede,
 * busca AJAX (GET servicos/buscar), isolamento multi-tenant e barreira de
 * permissao (403 para papel sem servico.criar).
 */
class ServicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pode_criar_servico_avulso(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $response = $this->post(route('servicos.store'), [
            'nome' => 'Corte de Cabelo',
            'duracao' => 45,
            'valor' => 60.00,
            'tipo' => TipoServico::Unico->value,
        ]);

        $response->assertRedirect(route('servicos.index'));
        $response->assertSessionHas('sucesso');

        $this->assertDatabaseHas('servicos', [
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Corte de Cabelo',
            'duracao' => 45,
            'tipo' => TipoServico::Unico->value,
            'qtd_etapas' => null,
        ]);
    }

    public function test_admin_pode_criar_servico_em_etapas(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $response = $this->post(route('servicos.store'), [
            'nome' => 'Pacote Massagem',
            'duracao' => 60,
            'valor' => 800.00,
            'tipo' => TipoServico::Etapas->value,
            'qtd_etapas' => 10,
        ]);

        $response->assertRedirect(route('servicos.index'));

        $this->assertDatabaseHas('servicos', [
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Pacote Massagem',
            'tipo' => TipoServico::Etapas->value,
            'qtd_etapas' => 10,
        ]);

        $servico = Servico::where('nome', 'Pacote Massagem')->firstOrFail();
        $this->assertTrue($servico->isEtapas());
    }

    public function test_servico_em_etapas_exige_qtd_etapas(): void
    {
        $this->criarRedeAutenticada();

        // tipo=etapas sem qtd_etapas viola required_if.
        $response = $this->from(route('servicos.create'))
            ->post(route('servicos.store'), [
                'nome' => 'Pacote Sem Etapas',
                'duracao' => 60,
                'valor' => 500.00,
                'tipo' => TipoServico::Etapas->value,
            ]);

        $response->assertSessionHasErrors(['qtd_etapas']);
        $this->assertDatabaseMissing('servicos', ['nome' => 'Pacote Sem Etapas']);
    }

    public function test_criar_servico_exige_campos_obrigatorios(): void
    {
        $this->criarRedeAutenticada();

        $response = $this->from(route('servicos.create'))
            ->post(route('servicos.store'), []);

        $response->assertSessionHasErrors(['nome', 'duracao', 'valor', 'tipo']);
        $this->assertDatabaseCount('servicos', 0);
    }

    public function test_listagem_index_carrega_com_escopo_de_rede(): void
    {
        $contexto = $this->criarRedeAutenticada();

        ServicoFactory::new()->avulso()->create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Servico da Rede',
        ]);

        $response = $this->get(route('servicos.index'));

        $response->assertOk();
        $response->assertSee('Servico da Rede');
    }

    public function test_admin_pode_editar_servico(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $servico = ServicoFactory::new()->avulso()->create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Servico Antigo',
            'valor' => 50.00,
            'duracao' => 30,
        ]);

        $response = $this->put(route('servicos.update', $servico), [
            'nome' => 'Servico Atualizado',
            'duracao' => 90,
            'valor' => 120.00,
            'tipo' => TipoServico::Unico->value,
        ]);

        $response->assertRedirect(route('servicos.index'));

        $this->assertDatabaseHas('servicos', [
            'id' => $servico->id,
            'nome' => 'Servico Atualizado',
            'duracao' => 90,
            'valor' => 120.00,
        ]);
    }

    public function test_admin_pode_excluir_servico(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $servico = ServicoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
        ]);

        $response = $this->delete(route('servicos.destroy', $servico));

        $response->assertRedirect(route('servicos.index'));

        // Servico usa SoftDeletes.
        $this->assertSoftDeleted('servicos', ['id' => $servico->id]);
        $this->assertNull(Servico::find($servico->id));
    }

    public function test_busca_ajax_retorna_servicos_que_casam_o_termo(): void
    {
        $contexto = $this->criarRedeAutenticada();

        ServicoFactory::new()->avulso()->create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Limpeza de Pele',
        ]);

        ServicoFactory::new()->etapas()->create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Drenagem Linfatica',
        ]);

        $response = $this->getJson(route('servicos.buscar', ['q' => 'Limpeza']));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['nome' => 'Limpeza de Pele']);
        $response->assertJsonMissing(['nome' => 'Drenagem Linfatica']);
    }

    public function test_busca_ajax_ignora_termo_curto(): void
    {
        $contexto = $this->criarRedeAutenticada();

        ServicoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'nome' => 'Servico Qualquer',
        ]);

        $response = $this->getJson(route('servicos.buscar', ['q' => 'S']));

        $response->assertOk();
        $response->assertExactJson([]);
    }

    public function test_isolamento_rede_a_nao_ve_servico_da_rede_b(): void
    {
        $redeA = $this->criarRede('A');
        $redeB = $this->criarRede('B');

        $servicoA = ServicoFactory::new()->create([
            'rede_id' => $redeA['rede']->id,
            'nome' => 'Servico A',
        ]);

        $servicoB = ServicoFactory::new()->create([
            'rede_id' => $redeB['rede']->id,
            'nome' => 'Servico B',
        ]);

        $this->actingAs($redeA['usuario']);
        session(['empresas_atuais' => [$redeA['empresa']->id]]);

        $servicos = Servico::all();
        $this->assertCount(1, $servicos);
        $this->assertSame($servicoA->id, $servicos->first()->id);
        $this->assertNull(Servico::find($servicoB->id));

        // Acessar servico de outra rede via rota retorna 404.
        $response = $this->get(route('servicos.show', $servicoB->id));
        $response->assertNotFound();
    }

    public function test_papel_sem_permissao_recebe_403_ao_criar_servico(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $papel = Role::firstOrCreate(['name' => 'Financeiro', 'guard_name' => 'web']);
        $papel->syncPermissions(['servico.ver']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $financeiro = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Financeiro');

        $this->actingAs($financeiro);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $response = $this->post(route('servicos.store'), [
            'nome' => 'Servico Proibido',
            'duracao' => 30,
            'valor' => 50.00,
            'tipo' => TipoServico::Unico->value,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('servicos', ['nome' => 'Servico Proibido']);
    }
}
