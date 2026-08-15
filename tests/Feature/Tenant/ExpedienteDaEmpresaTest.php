<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Modules\Tenant\Actions\CriarEmpresaAction;
use App\Modules\Tenant\DTOs\EmpresaData;
use App\Modules\Tenant\Models\{Empresa, HorarioAtendimento};
use App\Modules\Tenant\Services\ExpedienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * A configuração do expediente — a tela onde a unidade diz quando atende.
 *
 * Mora na edição da empresa porque é a mesma coisa: uma unidade é o cadastro
 * dela mais o horário em que ela funciona.
 */
class ExpedienteDaEmpresaTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    public function test_tela_de_edicao_mostra_os_sete_dias(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $resp = $this->get(route('empresas.edit', $contexto['empresa']));

        $resp->assertOk();
        $resp->assertSee('Expediente');
        foreach (HorarioAtendimento::DIAS as $dia) {
            $resp->assertSee($dia);
        }
    }

    /** Unidade antiga (anterior à tabela) não pode abrir a tela vazia. */
    public function test_tela_semeia_o_padrao_quando_a_unidade_nao_tem_expediente(): void
    {
        $contexto = $this->criarRedeAutenticada();
        HorarioAtendimento::where('empresa_id', $contexto['empresa']->id)->delete();

        $this->get(route('empresas.edit', $contexto['empresa']))->assertOk();

        $this->assertCount(7, app(ExpedienteService::class)->daEmpresa($contexto['empresa']->id));
    }

    public function test_salvar_expediente_altera_a_janela_da_unidade(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $empresa = $contexto['empresa'];

        $expediente = [];
        foreach (range(0, 6) as $dia) {
            $expediente[$dia] = ['hora_inicio' => '10:00', 'hora_fim' => '16:00'];
            if ($dia === 3) {
                $expediente[$dia]['ativo'] = '1';
            }
        }

        $this->put(route('empresas.update', $empresa), [
            'nome' => $empresa->nome,
            'expediente' => $expediente,
        ])->assertRedirect(route('empresas.index'));

        $linhas = app(ExpedienteService::class)->daEmpresa($empresa->id)->keyBy('dia_semana');

        $this->assertTrue($linhas[3]->ativo, 'Quarta foi marcada como dia de atendimento.');
        $this->assertFalse($linhas[1]->ativo, 'Segunda ficou desmarcada e precisa sair do expediente.');
        $this->assertSame('10:00', substr($linhas[3]->hora_inicio, 0, 5));
        $this->assertSame('16:00', substr($linhas[3]->hora_fim, 0, 5));
    }

    public function test_expediente_com_fim_antes_do_inicio_e_recusado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $empresa = $contexto['empresa'];

        $resp = $this->put(route('empresas.update', $empresa), [
            'nome' => $empresa->nome,
            'expediente' => [
                1 => ['ativo' => '1', 'hora_inicio' => '18:00', 'hora_fim' => '08:00'],
            ],
        ]);

        $resp->assertSessionHas('erro');
    }

    /** Unidade nova nasce operante: contas, formas de pagamento e expediente. */
    public function test_unidade_nova_nasce_com_expediente(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $empresa = app(CriarEmpresaAction::class)->executar(
            $contexto['rede'],
            EmpresaData::from(['nome' => 'Unidade Nova']),
        );

        // Sem escopo de propósito: a unidade recém-criada não está no contexto
        // de empresas da sessão, então a leitura normal não a alcançaria.
        $this->assertSame(
            7,
            HorarioAtendimento::withoutGlobalScopes()->where('empresa_id', $empresa->id)->count(),
            'Sem expediente a agenda da unidade nova nasceria sem regra de horario.'
        );
    }

    public function test_nao_edita_expediente_de_empresa_de_outra_rede(): void
    {
        $outra = $this->criarRede('outra');
        $this->criarRedeAutenticada();

        $this->get(route('empresas.edit', $outra['empresa']))->assertNotFound();

        $this->put(route('empresas.update', $outra['empresa']), [
            'nome' => 'Invadida',
            'expediente' => [1 => ['ativo' => '1', 'hora_inicio' => '00:00', 'hora_fim' => '23:59']],
        ])->assertNotFound();

        $this->assertSame(
            'Empresa outra',
            Empresa::withoutGlobalScopes()->findOrFail($outra['empresa']->id)->nome,
        );
    }
}
