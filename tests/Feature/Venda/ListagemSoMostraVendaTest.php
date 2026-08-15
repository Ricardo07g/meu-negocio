<?php

declare(strict_types=1);

namespace Tests\Feature\Venda;

use App\Enums\TipoFormaPagamento;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\Venda\Services\VendaService;
use Database\Factories\{AgendamentoFactory, ClienteFactory, ServicoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * A listagem de Vendas mostra venda — não agenda.
 *
 * `VendaService::listar` puxava TODO agendamento sem `venda_etapas_id` e o
 * mapeava com `valor = servico.valor`. Resultado: um agendamento criado direto
 * no calendário, que nunca gerou título nem baixa, aparecia na lista de vendas
 * exibindo um valor que ninguém recebeu — receita fantasma na tela.
 */
class ListagemSoMostraVendaTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    /** @param array{rede: mixed, empresa: mixed, usuario: mixed} $contexto */
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

    public function test_agendamento_sem_titulo_nao_aparece_na_listagem_de_vendas(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->atendimentoEmAberto($contexto);

        $vendas = app(VendaService::class)->listar();

        $this->assertCount(
            0,
            $vendas->items(),
            'Agendamento sem título é agenda, não venda — não pode aparecer como faturamento.'
        );
    }

    public function test_agendamento_passa_a_aparecer_depois_de_cobrado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->atendimentoEmAberto($contexto, 150.00);

        $this->post(route('vendas.store'), [
            'tipo_venda' => 'servico',
            'agendamento_id' => $agendamento->id,
            'recebimentos' => [
                [
                    'forma_pagamento_id' => FormaPagamento::ativos()
                        ->where('tipo', TipoFormaPagamento::Pix->value)->firstOrFail()->id,
                    'valor' => 150.00,
                ],
            ],
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
        ])->assertSessionMissing('erro');

        $vendas = app(VendaService::class)->listar();

        $this->assertCount(1, $vendas->items());
        $this->assertSame($agendamento->id, $vendas->items()[0]->id);
        $this->assertEquals(150.00, (float) $vendas->items()[0]->valor);
    }

    public function test_tela_de_vendas_nao_lista_atendimento_sem_cobranca(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $agendamento = $this->atendimentoEmAberto($contexto);

        $resp = $this->get(route('vendas.index'));

        $resp->assertOk();
        $resp->assertDontSee($agendamento->cliente->nome);
    }
}
