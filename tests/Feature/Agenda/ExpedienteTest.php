<?php

declare(strict_types=1);

namespace Tests\Feature\Agenda;

use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Tenant\Models\HorarioAtendimento;
use App\Modules\Tenant\Services\ExpedienteService;
use Carbon\Carbon;
use Database\Factories\{AgendamentoFactory, ClienteFactory, ServicoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * O expediente da unidade e o encaixe.
 *
 * Antes disto não existia horário nenhum: o `hourStart: 8 / hourEnd: 21` do
 * calendário era janela de visualização do Toast UI, e o servidor aceitava
 * qualquer datetime — 23h40 de domingo inclusive. Pior, o `reagendar` não
 * revalidava nem conflito, então bastava arrastar um evento por cima de outro
 * para empilhar dois clientes no mesmo atendente.
 */
class ExpedienteTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    /**
     * Expediente comercial de segunda a sexta. Sábado e domingo fechados —
     * assim o teste tem os dois lados da régua num cenário só.
     *
     * @param  array{rede: mixed, empresa: mixed, usuario: mixed}  $contexto
     */
    private function expedienteComercial(array $contexto, ?int $usuarioId = null): void
    {
        foreach (range(0, 6) as $dia) {
            HorarioAtendimento::create([
                'rede_id' => $contexto['rede']->id,
                'empresa_id' => $contexto['empresa']->id,
                'usuario_id' => $usuarioId,
                'dia_semana' => $dia,
                'hora_inicio' => '08:00',
                'hora_fim' => '18:00',
                'ativo' => $dia >= 1 && $dia <= 5,
            ]);
        }
    }

    /** @param array{rede: mixed, empresa: mixed, usuario: mixed} $contexto */
    private function payload(array $contexto, Carbon $inicio, array $extra = []): array
    {
        return array_merge([
            'cliente_id' => ClienteFactory::new()->create(['rede_id' => $contexto['rede']->id])->id,
            'servico_id' => ServicoFactory::new()->create([
                'rede_id' => $contexto['rede']->id,
                'duracao' => 60,
            ])->id,
            'atendente_id' => $contexto['usuario']->id,
            'inicio' => $inicio->format('Y-m-d H:i:s'),
        ], $extra);
    }

    private function proximaSegunda(string $hora): Carbon
    {
        return Carbon::parse('next monday '.$hora);
    }

    private function proximoDomingo(string $hora): Carbon
    {
        return Carbon::parse('next sunday '.$hora);
    }

    public function test_agendar_dentro_do_expediente_e_aceito(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->expedienteComercial($contexto);

        $resp = $this->postJson(
            route('agenda.criar-rapido'),
            $this->payload($contexto, $this->proximaSegunda('10:00'))
        );

        $resp->assertCreated();
        $this->assertFalse(Agendamento::firstOrFail()->fora_expediente);
    }

    public function test_agendar_depois_do_fechamento_e_recusado_com_codigo(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->expedienteComercial($contexto);

        $resp = $this->postJson(
            route('agenda.criar-rapido'),
            $this->payload($contexto, $this->proximaSegunda('22:00'))
        );

        $resp->assertStatus(422);
        // A tela distingue "nao pode" de "quer mesmo?" pelo codigo — texto de
        // mensagem nao e contrato.
        $resp->assertJsonPath('codigo', 'fora_expediente');
        $this->assertSame(0, Agendamento::count());
    }

    public function test_agendar_em_dia_fechado_e_recusado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->expedienteComercial($contexto);

        $resp = $this->postJson(
            route('agenda.criar-rapido'),
            $this->payload($contexto, $this->proximoDomingo('10:00'))
        );

        $resp->assertStatus(422);
        $resp->assertJsonPath('codigo', 'fora_expediente');
        $this->assertSame(0, Agendamento::count());
    }

    public function test_atendimento_que_ultrapassa_o_fechamento_e_recusado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->expedienteComercial($contexto);

        // Comeca 17:30 dentro, mas o servico de 60min termina 18:30 — fora.
        $resp = $this->postJson(
            route('agenda.criar-rapido'),
            $this->payload($contexto, $this->proximaSegunda('17:30'))
        );

        $resp->assertStatus(422);
        $resp->assertJsonPath('codigo', 'fora_expediente');
    }

    public function test_encaixe_autorizado_agenda_e_fica_marcado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->expedienteComercial($contexto);

        $resp = $this->postJson(
            route('agenda.criar-rapido'),
            $this->payload($contexto, $this->proximaSegunda('22:00'), ['forcar_horario' => true])
        );

        $resp->assertCreated();
        $this->assertTrue(
            Agendamento::firstOrFail()->fora_expediente,
            'O encaixe precisa ficar marcado — senao "furei o horario" vira indistinguivel de "o expediente mudou".'
        );
    }

    public function test_forcar_horario_sem_permissao_recebe_403(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->expedienteComercial($contexto);

        $recepcao = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Recepcao');
        $recepcao->givePermissionTo(['agendamento.criar', 'agendamento.ver']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($recepcao);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $payload = $this->payload($contexto, $this->proximaSegunda('22:00'), ['forcar_horario' => true]);
        $payload['atendente_id'] = $recepcao->id;

        $this->postJson(route('agenda.criar-rapido'), $payload)->assertStatus(403);
        $this->assertSame(0, Agendamento::count());
    }

    /**
     * Rede de segurança: unidade sem expediente configurado não restringe.
     * Recusar tudo deixaria a agenda inutilizável — inclusive para consertar.
     */
    /**
     * O resumo da barra lateral sai em linhas, uma por dia.
     *
     * Antes era uma frase unica com os dias colados por " · ": seis faixas na
     * mesma linha viravam um paragrafo que ninguem le de relance, justamente na
     * informacao que a recepcao consulta o dia inteiro.
     */
    public function test_resumo_do_expediente_traz_uma_linha_por_dia_ativo(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->expedienteComercial($contexto);

        $linhas = app(ExpedienteService::class)->resumoPorDia((int) $contexto['empresa']->id);

        // Segunda a sexta ativos; sabado e domingo ficam de fora.
        $this->assertCount(5, $linhas);
        $this->assertSame('Seg 08:00–18:00', $linhas->first());

        $resp = $this->get(route('agenda.index'))->assertOk();
        foreach ($linhas as $linha) {
            $resp->assertSee('<div>'.$linha.'</div>', false);
        }
    }

    public function test_resumo_sem_expediente_configurado_avisa_em_vez_de_vir_vazio(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $linhas = app(ExpedienteService::class)->resumoPorDia((int) $contexto['empresa']->id);

        $this->assertSame(['Sem expediente configurado'], $linhas->all());
    }

    public function test_unidade_sem_expediente_configurado_nao_restringe(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $this->postJson(
            route('agenda.criar-rapido'),
            $this->payload($contexto, $this->proximoDomingo('23:00'))
        )->assertCreated();
    }

    /** O horário do atendente vence o da empresa quando existe. */
    public function test_expediente_do_atendente_vence_o_da_empresa(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->expedienteComercial($contexto);

        // O atendente atende no domingo, mesmo com a unidade fechada.
        HorarioAtendimento::create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
            'dia_semana' => 0,
            'hora_inicio' => '09:00',
            'hora_fim' => '13:00',
            'ativo' => true,
        ]);

        $this->postJson(
            route('agenda.criar-rapido'),
            $this->payload($contexto, $this->proximoDomingo('10:00'))
        )->assertCreated();
    }

    /**
     * O bug do drag-and-drop: `reagendar` não revalidava nada, então arrastar
     * um evento por cima de outro empilhava dois clientes no mesmo atendente.
     */
    public function test_reagendar_para_cima_de_outro_atendimento_e_recusado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->expedienteComercial($contexto);

        $ocupado = $this->proximaSegunda('10:00');

        AgendamentoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'atendente_id' => $contexto['usuario']->id,
            'inicio' => $ocupado,
            'fim' => $ocupado->copy()->addHour(),
        ]);

        $outro = AgendamentoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'atendente_id' => $contexto['usuario']->id,
            'inicio' => $ocupado->copy()->addHours(4),
            'fim' => $ocupado->copy()->addHours(5),
        ]);

        $resp = $this->patchJson(route('agenda.reagendar', $outro), [
            'inicio' => $ocupado->format('Y-m-d H:i:s'),
            'fim' => $ocupado->copy()->addHour()->format('Y-m-d H:i:s'),
        ]);

        $resp->assertStatus(422);
        $this->assertSame(
            $ocupado->copy()->addHours(4)->format('Y-m-d H:i'),
            $outro->fresh()->inicio->format('Y-m-d H:i'),
            'Reagendamento recusado nao pode ter movido o atendimento.'
        );
    }

    public function test_reagendar_para_fora_do_expediente_e_recusado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->expedienteComercial($contexto);

        $inicio = $this->proximaSegunda('10:00');
        $agendamento = AgendamentoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'atendente_id' => $contexto['usuario']->id,
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addHour(),
        ]);

        $resp = $this->patchJson(route('agenda.reagendar', $agendamento), [
            'inicio' => $this->proximoDomingo('10:00')->format('Y-m-d H:i:s'),
            'fim' => $this->proximoDomingo('11:00')->format('Y-m-d H:i:s'),
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonPath('codigo', 'fora_expediente');
    }

    public function test_reagendar_com_encaixe_autorizado_marca_o_atendimento(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->expedienteComercial($contexto);

        $inicio = $this->proximaSegunda('10:00');
        $agendamento = AgendamentoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'atendente_id' => $contexto['usuario']->id,
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addHour(),
        ]);

        $this->patchJson(route('agenda.reagendar', $agendamento), [
            'inicio' => $this->proximoDomingo('10:00')->format('Y-m-d H:i:s'),
            'fim' => $this->proximoDomingo('11:00')->format('Y-m-d H:i:s'),
            'forcar_horario' => true,
        ])->assertOk();

        $this->assertTrue($agendamento->fresh()->fora_expediente);
    }

    public function test_expediente_nao_vaza_entre_empresas(): void
    {
        $outra = $this->criarRede('outra');
        $this->expedienteComercial($outra);

        $contexto = $this->criarRedeAutenticada();

        $this->assertFalse(
            app(ExpedienteService::class)->configurado($contexto['empresa']->id),
            'Expediente de outra rede nao pode contar como configuracao desta.'
        );
        $this->assertCount(0, app(ExpedienteService::class)->daEmpresa($contexto['empresa']->id));
    }

    public function test_agenda_mostra_o_expediente_configurado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->expedienteComercial($contexto);

        $this->get(route('agenda.index'))
            ->assertOk()
            ->assertSee('Expediente')
            ->assertSee('08:00–18:00');
    }
}
