<?php

namespace Tests\Feature\Agenda;

use App\Enums\StatusAgendamento;
use App\Modules\Agenda\Models\Agendamento;
use Database\Factories\ClienteFactory;
use Database\Factories\ServicoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre a criacao de agendamento via endpoint HTTP `agenda/criar-rapido`
 * (POST, JSON) e a regra de calculo automatico de `fim` quando nao
 * informado (CriarAgendamentoAction usa servico->duracao).
 *
 * Foco: caminho feliz cliente + servico + atendente + data/hora.
 */
class CriarAgendamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_agendamento_com_sucesso(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $cliente = ClienteFactory::new()->create(['rede_id' => $rede->id]);
        $servico = ServicoFactory::new()->create(['rede_id' => $rede->id, 'duracao' => 60]);
        $atendente = $contexto['usuario']; // Admin atende=true

        $inicio = now()->addDay()->setTime(10, 0);

        $resp = $this->postJson(route('agenda.criar-rapido'), [
            'cliente_id' => $cliente->id,
            'servico_id' => $servico->id,
            'atendente_id' => $atendente->id,
            'inicio' => $inicio->format('Y-m-d H:i:s'),
            // fim omitido de proposito — deve ser calculado via duracao.
        ]);

        $resp->assertCreated();

        $agendamento = Agendamento::query()->latest('id')->firstOrFail();

        $this->assertSame($resp->json('id'), $agendamento->id);
        $this->assertSame($cliente->id, $agendamento->cliente_id);
        $this->assertSame($servico->id, $agendamento->servico_id);
        $this->assertSame($atendente->id, $agendamento->atendente_id);
        $this->assertSame(StatusAgendamento::Agendado, $agendamento->status, 'Novo agendamento deve nascer com status Agendado.');
        $this->assertSame($contexto['empresa']->id, $agendamento->empresa_id, 'empresa_id deve ser resolvido pelo EmpresaTrait via sessao.');
        $this->assertSame($rede->id, $agendamento->rede_id);

        // fim = inicio + 60 min (duracao do servico)
        $this->assertSame(
            $inicio->copy()->addMinutes(60)->format('Y-m-d H:i'),
            $agendamento->fim->format('Y-m-d H:i'),
            'fim deveria ser calculado a partir da duracao do servico.'
        );
    }

    public function test_falha_ao_criar_sem_atendente(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $cliente = ClienteFactory::new()->create(['rede_id' => $rede->id]);
        $servico = ServicoFactory::new()->create(['rede_id' => $rede->id]);

        $resp = $this->postJson(route('agenda.criar-rapido'), [
            'cliente_id' => $cliente->id,
            'servico_id' => $servico->id,
            // atendente_id omitido — NOT NULL / required.
            'inicio' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $resp->assertStatus(422);
        $this->assertSame(0, Agendamento::query()->count(), 'Nenhum agendamento deveria ter sido criado sem atendente.');
    }
}
