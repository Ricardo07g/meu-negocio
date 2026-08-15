<?php

declare(strict_types=1);

namespace Tests\Feature\Agenda;

use App\Enums\{SituacaoFinanceiraAgendamento, StatusAgendamento, StatusPagamento, StatusParcela, TipoFormaPagamento};
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Tenant\Models\{Empresa, Rede};
use App\Modules\Usuario\Models\Usuario;
use Database\Factories\{AgendamentoFactory, CaixaFactory, ClienteFactory, PagamentoFactory, ServicoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * A ponte que faltava entre agenda e financeiro.
 *
 * O agendamento criado direto no calendario nascia sem titulo, e finalizar so
 * trocava o status: o atendimento acontecia e nunca virava receita. Agora ele e
 * cobrado pela tela de venda em modo cobranca (`vendas/nova?agendamento=X`),
 * que reusa o bloco de recebimento inteiro — split de formas, crediario, carne.
 */
class CobrarAtendimentoTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    private function formaId(TipoFormaPagamento $tipo): int
    {
        return FormaPagamento::ativos()->where('tipo', $tipo->value)->firstOrFail()->id;
    }

    /**
     * Agendamento em aberto, sem titulo — o estado que a agenda produz.
     *
     * @param  array{rede: Rede, empresa: Empresa, usuario: Usuario}  $contexto
     */
    private function atendimentoEmAberto(array $contexto, float $valor = 150.00): Agendamento
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

    public function test_cobrar_a_vista_gera_titulo_baixado_e_finaliza_o_atendimento(): void
    {
        $contexto = $this->criarRedeAutenticada();
        CaixaFactory::new()->aberto()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
        ]);

        $agendamento = $this->atendimentoEmAberto($contexto, 150.00);

        $resp = $this->post(route('vendas.store'), [
            'tipo_venda' => 'servico',
            'agendamento_id' => $agendamento->id,
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Dinheiro), 'valor' => 150.00],
            ],
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('sucesso');
        $resp->assertSessionMissing('erro');

        $agendamento->refresh();
        $this->assertSame(StatusAgendamento::Finalizado, $agendamento->status);
        $this->assertSame(SituacaoFinanceiraAgendamento::Pago, $agendamento->situacaoFinanceira());

        $pagamento = Pagamento::where('agendamento_id', $agendamento->id)->firstOrFail();
        $this->assertEquals(150.00, (float) $pagamento->valor_total);
        $this->assertSame(StatusParcela::Pago, $pagamento->parcelas->first()->status);

        // Cobrar nao cria um segundo atendimento — usa o que ja existe na agenda.
        $this->assertSame(1, Agendamento::count());
    }

    public function test_cobrar_a_prazo_deixa_o_atendimento_em_contas_a_receber(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->atendimentoEmAberto($contexto, 300.00);

        $resp = $this->post(route('vendas.store'), [
            'tipo_venda' => 'servico',
            'agendamento_id' => $agendamento->id,
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Crediario), 'valor' => 300.00],
            ],
            'forma_recebimento_prazo' => 'carne',
            'numero_parcelas' => 3,
            'primeiro_vencimento' => now()->addMonth()->format('Y-m-d'),
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('sucesso');

        $agendamento->refresh();
        $this->assertSame(StatusAgendamento::Finalizado, $agendamento->status);
        $this->assertSame(SituacaoFinanceiraAgendamento::AReceber, $agendamento->situacaoFinanceira());

        $pagamento = Pagamento::where('agendamento_id', $agendamento->id)->firstOrFail();
        $this->assertSame(StatusPagamento::Pendente, $pagamento->status);
        $this->assertCount(3, $pagamento->parcelas);
    }

    /**
     * Recusa: dois titulos para o mesmo atendimento seria cobrar o cliente
     * duas vezes pelo mesmo servico.
     */
    public function test_cobrar_atendimento_ja_cobrado_e_recusado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->atendimentoEmAberto($contexto, 150.00);

        PagamentoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'cliente_id' => $agendamento->cliente_id,
            'agendamento_id' => $agendamento->id,
            'valor_total' => 150.00,
        ]);

        $resp = $this->post(route('vendas.store'), [
            'tipo_venda' => 'servico',
            'agendamento_id' => $agendamento->id,
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Pix), 'valor' => 150.00],
            ],
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertSessionHas('erro');
        $this->assertSame(
            1,
            Pagamento::where('agendamento_id', $agendamento->id)->count(),
            'O atendimento nao pode acumular dois titulos.'
        );
    }

    /**
     * O valor cobrado e o do servico agendado. Se viesse do form, bastava
     * trocar o numero no POST para cobrar R$ 1 por um servico de R$ 150.
     */
    public function test_recebimento_que_nao_cobre_o_valor_do_servico_e_recusado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->atendimentoEmAberto($contexto, 150.00);

        $resp = $this->post(route('vendas.store'), [
            'tipo_venda' => 'servico',
            'agendamento_id' => $agendamento->id,
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Pix), 'valor' => 1.00],
            ],
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertSessionHasErrors('recebimentos');
        $this->assertSame(0, Pagamento::count());
    }

    public function test_atendimento_de_outra_rede_nao_pode_ser_cobrado(): void
    {
        $outra = $this->criarRede('outra');
        $alheio = $this->atendimentoEmAberto($outra, 150.00);

        $contexto = $this->criarRedeAutenticada();

        $resp = $this->post(route('vendas.store'), [
            'tipo_venda' => 'servico',
            'agendamento_id' => $alheio->id,
            'recebimentos' => [
                ['forma_pagamento_id' => $this->formaId(TipoFormaPagamento::Pix), 'valor' => 150.00],
            ],
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $resp->assertSessionHasErrors('agendamento_id');
        $this->assertSame(
            0,
            Pagamento::withoutGlobalScopes()->where('agendamento_id', $alheio->id)->count(),
            'Atendimento de outra rede nao pode gerar titulo.'
        );
    }

    public function test_tela_de_cobranca_mostra_o_atendimento_e_o_valor(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->atendimentoEmAberto($contexto, 150.00);

        $resp = $this->get(route('vendas.create', ['agendamento' => $agendamento->id]));

        $resp->assertOk();
        $resp->assertSee('Atendimento realizado');
        $resp->assertSee($agendamento->cliente->nome);
        $resp->assertSee('150,00');
    }

    public function test_tela_de_cobranca_recusa_atendimento_ja_cobrado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->atendimentoEmAberto($contexto, 150.00);

        PagamentoFactory::new()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'agendamento_id' => $agendamento->id,
        ]);

        $resp = $this->get(route('vendas.create', ['agendamento' => $agendamento->id]));

        $resp->assertRedirect(route('agenda.index'));
        $resp->assertSessionHas('erro');
    }

    public function test_detalhe_do_agendamento_mostra_a_situacao_de_cobranca(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->atendimentoEmAberto($contexto, 150.00);

        $this->get(route('agenda.show', $agendamento))
            ->assertOk()
            ->assertSee('Cobrança')
            ->assertSee('A cobrar');
    }

    public function test_papel_sem_permissao_recebe_403_ao_abrir_a_cobranca(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->atendimentoEmAberto($contexto, 150.00);

        $semPermissao = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Recepcao');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($semPermissao);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $this->get(route('vendas.create', ['agendamento' => $agendamento->id]))
            ->assertForbidden();
    }
}
