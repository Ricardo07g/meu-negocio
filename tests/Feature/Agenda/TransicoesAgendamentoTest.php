<?php

declare(strict_types=1);

namespace Tests\Feature\Agenda;

use App\Enums\{MotivoSemCobranca, SituacaoFinanceiraAgendamento, StatusAgendamento};
use App\Modules\Agenda\Models\Agendamento;
use Database\Factories\{AgendamentoFactory, PagamentoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre as transicoes de status via endpoints PATCH do AgendaController:
 *  - confirmar  (agendado -> confirmado)
 *  - finalizar  (agendado/confirmado -> finalizado)
 *  - cancelar   (-> cancelado, exceto se ja finalizado)
 *
 * As acoes envolvidas: AgendamentoService::confirmar,
 * FinalizarAgendamentoAction e CancelarAgendamentoAction.
 */
class TransicoesAgendamentoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cria um agendamento na rede/empresa do contexto autenticado.
     */
    private function agendamentoNoContexto(array $contexto, array $estados = []): Agendamento
    {
        return AgendamentoFactory::new()->create(array_merge([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'atendente_id' => $contexto['usuario']->id,
        ], $estados));
    }

    public function test_confirmar_agendamento(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->agendamentoNoContexto($contexto);

        $this->assertSame(StatusAgendamento::Agendado, $agendamento->status);

        $resp = $this->patch(route('agenda.confirmar', $agendamento));
        $resp->assertRedirect();

        $this->assertSame(StatusAgendamento::Confirmado, $agendamento->fresh()->status);
    }

    public function test_finalizar_agendamento_confirmado_sem_cobrar_registra_o_motivo(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->agendamentoNoContexto($contexto, ['status' => StatusAgendamento::Confirmado]);

        $resp = $this->patch(route('agenda.finalizar', $agendamento), [
            'motivo_sem_cobranca' => MotivoSemCobranca::Cortesia->value,
        ]);
        $resp->assertRedirect();

        $agendamento->refresh();
        $this->assertSame(StatusAgendamento::Finalizado, $agendamento->status);
        $this->assertSame(MotivoSemCobranca::Cortesia, $agendamento->motivo_sem_cobranca);
        $this->assertSame(SituacaoFinanceiraAgendamento::SemCobranca, $agendamento->situacaoFinanceira());
    }

    /**
     * A recusa e o ponto da feature: sem titulo e sem motivo, finalizar deixava
     * o atendimento no historico sem nunca virar receita — e "nao cobrei de
     * proposito" ficava indistinguivel de "esqueci de cobrar".
     */
    public function test_finalizar_sem_titulo_e_sem_motivo_e_recusado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->agendamentoNoContexto($contexto, ['status' => StatusAgendamento::Confirmado]);

        $resp = $this->patchJson(route('agenda.finalizar', $agendamento));
        $resp->assertStatus(422);

        $this->assertSame(
            StatusAgendamento::Confirmado,
            $agendamento->fresh()->status,
            'Sem desfecho declarado o atendimento nao pode ser encerrado.'
        );
    }

    /**
     * Quem ja tem titulo (venda pre-paga) finaliza direto: cobrar de novo ou
     * pedir justificativa seria pedir duas vezes a mesma resposta.
     */
    public function test_finalizar_agendamento_ja_cobrado_dispensa_motivo(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->agendamentoNoContexto($contexto, ['status' => StatusAgendamento::Confirmado]);

        PagamentoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'cliente_id' => $agendamento->cliente_id,
            'agendamento_id' => $agendamento->id,
        ]);

        $resp = $this->patch(route('agenda.finalizar', $agendamento));
        $resp->assertRedirect();

        $agendamento->refresh();
        $this->assertSame(StatusAgendamento::Finalizado, $agendamento->status);
        $this->assertNull(
            $agendamento->motivo_sem_cobranca,
            'Titulo e motivo sao mutuamente exclusivos.'
        );
    }

    public function test_cancelar_agendamento(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->agendamentoNoContexto($contexto);

        $resp = $this->patch(route('agenda.cancelar', $agendamento));
        $resp->assertRedirect();

        $this->assertSame(StatusAgendamento::Cancelado, $agendamento->fresh()->status);
    }

    public function test_nao_cancela_agendamento_finalizado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->agendamentoNoContexto($contexto, ['status' => StatusAgendamento::Finalizado]);

        // CancelarAgendamentoAction lanca ValidationException; em requisicao
        // JSON o Laravel a renderiza como 422 antes do catch generico do
        // controller, e o status do agendamento permanece inalterado.
        $resp = $this->patchJson(route('agenda.cancelar', $agendamento));
        $resp->assertStatus(422);

        $this->assertSame(
            StatusAgendamento::Finalizado,
            $agendamento->fresh()->status,
            'Agendamento finalizado nao pode ser cancelado — status deve permanecer Finalizado.'
        );
    }

    public function test_nao_finaliza_agendamento_cancelado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->agendamentoNoContexto($contexto, ['status' => StatusAgendamento::Cancelado]);

        // FinalizarAgendamentoAction so aceita Agendado/Confirmado e lanca
        // ValidationException — renderizada como 422 em requisicao JSON.
        $resp = $this->patchJson(route('agenda.finalizar', $agendamento));
        $resp->assertStatus(422);

        $this->assertSame(
            StatusAgendamento::Cancelado,
            $agendamento->fresh()->status,
            'Agendamento cancelado nao pode ser finalizado.'
        );
    }
}
