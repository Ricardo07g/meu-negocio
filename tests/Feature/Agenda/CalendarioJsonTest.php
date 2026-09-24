<?php

declare(strict_types=1);

namespace Tests\Feature\Agenda;

use Database\Factories\AgendamentoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * O endpoint que alimenta o calendario (`agenda.json`) — ate aqui sem nenhum
 * teste. Cada troca de semana na tela e uma chamada a ele com a janela nova, no
 * formato que o `calendar.js` envia: hora de parede, do primeiro dia 00:00:00
 * ao ultimo 23:59:59.
 *
 * O que ele precisa garantir: a janela pedida devolve exatamente os
 * atendimentos dela (inclusive o fim do ultimo dia, que ja sumiu da tela uma
 * vez), a semana seguinte devolve os da semana seguinte, e nada atravessa rede
 * nem empresa.
 */
class CalendarioJsonTest extends TestCase
{
    use RefreshDatabase;

    /** Segunda a domingo, como o calendario pede (`startDayOfWeek: 1`). */
    private const SEMANA = ['start' => '2026-09-21 00:00:00', 'end' => '2026-09-27 23:59:59'];

    private const PROXIMA_SEMANA = ['start' => '2026-09-28 00:00:00', 'end' => '2026-10-04 23:59:59'];

    private function pedirJanela(array $janela): TestResponse
    {
        return $this->getJson(route('agenda.json', $janela));
    }

    /** @return list<string> */
    private function idsDosEventos(TestResponse $resp): array
    {
        return collect($resp->json('events'))->pluck('id')->sort()->values()->all();
    }

    private function agendar(array $contexto, string $inicio, array $extra = []): string
    {
        $ag = AgendamentoFactory::new()->create(array_merge([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'atendente_id' => $contexto['usuario']->id,
            'inicio' => $inicio,
            'fim' => date('Y-m-d H:i:s', strtotime($inicio.' +1 hour')),
        ], $extra));

        return (string) $ag->id;
    }

    public function test_semana_devolve_so_os_atendimentos_dela_inclusive_o_fim_do_domingo(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $segunda = $this->agendar($contexto, '2026-09-21 10:00:00');
        $domingoANoite = $this->agendar($contexto, '2026-09-27 21:00:00');
        $this->agendar($contexto, '2026-09-20 23:00:00'); // domingo da semana anterior
        $this->agendar($contexto, '2026-09-28 08:00:00'); // segunda da semana seguinte

        $resp = $this->pedirJanela(self::SEMANA)->assertOk();

        $this->assertSame(
            collect([$segunda, $domingoANoite])->sort()->values()->all(),
            $this->idsDosEventos($resp),
            'A semana deve trazer do inicio da segunda ao fim do domingo, e nada fora disso.'
        );
    }

    public function test_avancar_a_semana_traz_os_atendimentos_da_semana_seguinte(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $this->agendar($contexto, '2026-09-23 10:00:00');
        $proxima = $this->agendar($contexto, '2026-09-30 15:30:00');

        $resp = $this->pedirJanela(self::PROXIMA_SEMANA)->assertOk();

        $this->assertSame([$proxima], $this->idsDosEventos($resp));
        $this->assertSame('2026-09-30T15:30:00', $resp->json('events.0.start'), 'Hora de parede, sem fuso — o calendario desenha exatamente o que foi gravado.');
        $this->assertSame((string) $contexto['usuario']->id, $resp->json('events.0.calendarId'));
    }

    public function test_calendars_traz_os_atendentes_para_colorir_os_eventos(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $resp = $this->pedirJanela(self::SEMANA)->assertOk();

        $this->assertContains(
            (string) $contexto['usuario']->id,
            collect($resp->json('calendars'))->pluck('id')->all(),
            'Sem o atendente em `calendars`, o Toast UI nao tem com o que pintar o evento.'
        );
    }

    public function test_janela_nao_traz_atendimento_de_outra_rede(): void
    {
        $redeA = $this->criarRede('A');
        $redeB = $this->criarRede('B');

        $daRedeA = $this->agendar($redeA, '2026-09-22 10:00:00');
        $this->agendar($redeB, '2026-09-22 10:00:00');

        $this->actingAs($redeA['usuario']);
        session(['empresas_atuais' => [$redeA['empresa']->id]]);

        $this->assertSame([$daRedeA], $this->idsDosEventos($this->pedirJanela(self::SEMANA)->assertOk()));
    }

    public function test_janela_com_empresa_em_contexto_nao_traz_atendimento_de_outra_unidade(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $outraUnidade = $this->criarEmpresaExtra($contexto['rede']->id, 'Unidade B');

        $daUnidadeA = $this->agendar($contexto, '2026-09-22 10:00:00');
        $this->agendar($contexto, '2026-09-22 14:00:00', ['empresa_id' => $outraUnidade->id]);

        session([
            'empresas_atuais' => [$contexto['empresa']->id, $outraUnidade->id],
            'empresa_contexto_atual' => $contexto['empresa']->id,
        ]);

        $this->assertSame([$daUnidadeA], $this->idsDosEventos($this->pedirJanela(self::SEMANA)->assertOk()));
    }

    public function test_papel_sem_permissao_recebe_403_no_json_do_calendario(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $semPermissao = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Recepcao');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($semPermissao);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $this->pedirJanela(self::SEMANA)->assertForbidden();
    }
}
