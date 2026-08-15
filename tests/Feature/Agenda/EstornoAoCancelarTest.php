<?php

declare(strict_types=1);

namespace Tests\Feature\Agenda;

use App\Enums\{StatusAgendamento, StatusPagamento, StatusParcela, TipoFormaPagamento};
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Conta\Models\Lancamento;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\Pagamento\Models\Pagamento;
use Database\Factories\{AgendamentoFactory, CaixaFactory, ClienteFactory, ServicoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * Cancelar pela agenda passa a desfazer o dinheiro de verdade.
 *
 * Antes, `CancelarAgendamentoAction` marcava o titulo como Estornado na mao: o
 * rotulo mudava, mas o dinheiro continuava na gaveta (nenhum contra-lancamento)
 * e as parcelas a receber sobreviviam ao cancelamento. Cancelar venda e cancelar
 * agendamento sao o mesmo evento financeiro — agora passam pelo mesmo caminho.
 */
class EstornoAoCancelarTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    private function formaId(TipoFormaPagamento $tipo): int
    {
        return FormaPagamento::ativos()->where('tipo', $tipo->value)->firstOrFail()->id;
    }

    /** @param array{rede: mixed, empresa: mixed, usuario: mixed} $contexto */
    private function atendimentoEmAberto(array $contexto, float $valor): Agendamento
    {
        $cliente = ClienteFactory::new()->create(['rede_id' => $contexto['rede']->id]);
        $servico = ServicoFactory::new()->avulso()->create([
            'rede_id' => $contexto['rede']->id,
            'valor' => $valor,
        ]);

        return AgendamentoFactory::new()->confirmado()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'cliente_id' => $cliente->id,
            'servico_id' => $servico->id,
            'atendente_id' => $contexto['usuario']->id,
        ]);
    }

    /** Cobra o atendimento em dinheiro (unica forma que move a gaveta). */
    private function cobrarEmDinheiro(Agendamento $agendamento, float $valor): void
    {
        $this->post(route('vendas.store'), [
            'tipo_venda' => 'servico',
            'agendamento_id' => $agendamento->id,
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Dinheiro), 'valor' => $valor],
            ],
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ])->assertSessionMissing('erro');
    }

    public function test_cancelar_atendimento_pago_em_dinheiro_gera_contra_lancamento(): void
    {
        $contexto = $this->criarRedeAutenticada();
        CaixaFactory::new()->aberto()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
        ]);

        $agendamento = $this->atendimentoEmAberto($contexto, 200.00);
        $this->cobrarEmDinheiro($agendamento, 200.00);

        // Cobrar finaliza o atendimento, entao a reversao vem da tela de vendas:
        // o servico foi prestado, so o dinheiro volta.
        $this->patch(route('vendas.cancelar-unico', $agendamento))
            ->assertRedirect()
            ->assertSessionMissing('erro');

        $this->assertSame(
            StatusAgendamento::Finalizado,
            $agendamento->fresh()->status,
            'Estornar a cobranca nao desfaz o atendimento — ele aconteceu.'
        );

        $pagamento = Pagamento::with('parcelas.baixas')->where('agendamento_id', $agendamento->id)->firstOrFail();
        $this->assertSame(StatusPagamento::Estornado, $pagamento->status);

        $baixa = $pagamento->parcelas->first()->baixas->first();
        $this->assertNotNull($baixa->estornado_em, 'A baixa precisa ficar marcada como estornada.');

        $this->assertSame(
            1,
            Lancamento::where('categoria', 'estorno')->where('baixa_pagamento_id', $baixa->id)->count(),
            'Dinheiro que entrou na gaveta precisa de contra-lancamento ao ser estornado.'
        );
    }

    public function test_cancelar_atendimento_a_prazo_pela_agenda_cancela_as_parcelas(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->atendimentoEmAberto($contexto, 300.00);

        $this->post(route('vendas.store'), [
            'tipo_venda' => 'servico',
            'agendamento_id' => $agendamento->id,
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Crediario), 'valor' => 300.00],
            ],
            'forma_recebimento_prazo' => 'carne',
            'numero_parcelas' => 3,
            'primeiro_vencimento' => now()->addMonth()->format('Y-m-d'),
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ])->assertSessionMissing('erro');

        // Volta ao estado cancelavel: a cobranca finaliza, e finalizado nao cancela.
        $agendamento->refresh()->update(['status' => StatusAgendamento::Confirmado]);

        $this->patchJson(route('agenda.cancelar', $agendamento))->assertOk();

        $pagamento = Pagamento::with('parcelas')->where('agendamento_id', $agendamento->id)->firstOrFail();
        $this->assertSame(StatusPagamento::Estornado, $pagamento->status);
        $this->assertTrue(
            $pagamento->parcelas->every(fn ($p) => $p->status === StatusParcela::Cancelado),
            'Parcelas a receber de um atendimento cancelado nao podem continuar cobraveis.'
        );
    }

    /**
     * Recusa: estornar dinheiro de um caixa fechado furaria a conferencia da
     * gaveta. O usuario reabre o caixa da data e tenta de novo.
     */
    public function test_cancelar_com_caixa_fechado_e_recusado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        CaixaFactory::new()->aberto()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
        ]);

        $agendamento = $this->atendimentoEmAberto($contexto, 200.00);
        $this->cobrarEmDinheiro($agendamento, 200.00);

        Caixa::query()->update([
            'status' => 'fechado',
            'fechado_em' => now(),
            'fechado_por' => $contexto['usuario']->id,
        ]);

        $agendamento->refresh()->update(['status' => StatusAgendamento::Confirmado]);

        $resp = $this->patchJson(route('agenda.cancelar', $agendamento));
        $resp->assertStatus(422);

        $this->assertSame(
            StatusAgendamento::Confirmado,
            $agendamento->fresh()->status,
            'Estorno recusado nao pode deixar o agendamento cancelado pela metade.'
        );
        $this->assertSame(
            StatusPagamento::Pago,
            Pagamento::where('agendamento_id', $agendamento->id)->firstOrFail()->status,
        );
    }
}
