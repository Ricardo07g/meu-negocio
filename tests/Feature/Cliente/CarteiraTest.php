<?php

declare(strict_types=1);

namespace Tests\Feature\Cliente;

use App\Enums\StatusVendaProduto;
use App\Modules\Ia\Drivers\FakeIa;
use App\Modules\Ia\Enums\StatusAnalise;
use App\Modules\Ia\Models\AnaliseIa;
use App\Modules\Tenant\Models\Plano;
use App\Modules\Venda\Models\VendaProduto;
use Database\Factories\ClienteFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * A tela da carteira e o endpoint AJAX de analise.
 *
 * O ponto mais importante coberto aqui: **a pagina continua util sem IA**. Provedor
 * desligado, cota estourada ou plano sem a feature nao podem quebrar a segmentacao.
 */
class CarteiraTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    private array $contexto;

    protected function setUp(): void
    {
        parent::setUp();
        FakeIa::resetar();
        $this->contexto = $this->criarRedeAutenticada();
    }

    private function semearCarteira(int $clientes = 6): void
    {
        for ($i = 1; $i <= $clientes; $i++) {
            $cliente = ClienteFactory::new()->create([
                'rede_id' => $this->contexto['rede']->id,
                'nome' => "Cliente {$i}",
            ]);

            VendaProduto::create([
                'rede_id' => $this->contexto['rede']->id,
                'empresa_id' => $this->contexto['empresa']->id,
                'cliente_id' => $cliente->id,
                'usuario_id' => $this->contexto['usuario']->id,
                'data' => now()->subDays(10 * $i)->toDateString(),
                'subtotal' => 100 * $i,
                'desconto' => 0,
                'acrescimo' => 0,
                'valor_total' => 100 * $i,
                'status' => StatusVendaProduto::Ativa->value,
            ]);
        }
    }

    public function test_tela_lista_segmentos_e_clientes(): void
    {
        $this->semearCarteira();

        $this->get(route('clientes.carteira'))
            ->assertOk()
            ->assertSee('Segmentos')
            ->assertSee('Cliente 1')
            ->assertSee('Analisar com IA');
    }

    /**
     * O JS vive num `@push('js')`. Se o nome do stack estiver errado, o script some da
     * pagina sem erro nenhum: a tela responde 200, o botao aparece e o clique nao faz
     * nada — o modo de falha exato que ja custou dois bugs neste repo.
     */
    public function test_script_do_botao_e_injetado_na_pagina(): void
    {
        $this->semearCarteira();

        $this->get(route('clientes.carteira'))
            ->assertOk()
            ->assertSee('btn-analisar-ia')
            ->assertSee('clientes/carteira/analisar');
    }

    public function test_tela_funciona_com_a_ia_desligada(): void
    {
        $this->semearCarteira();
        FakeIa::$ativo = false;

        $this->get(route('clientes.carteira'))
            ->assertOk()
            ->assertSee('Segmentos')
            ->assertSee('Cliente 1')
            ->assertDontSee('Analisar com IA');
    }

    public function test_plano_sem_ia_esconde_o_botao_mas_mantem_a_segmentacao(): void
    {
        $this->semearCarteira();
        $this->contexto['empresa']->update([
            'plano_id' => Plano::where('slug', Plano::GRATIS)->firstOrFail()->id,
        ]);

        $this->get(route('clientes.carteira'))
            ->assertOk()
            ->assertSee('Segmentos')
            ->assertSee('plano Pro')
            ->assertDontSee('Analisar com IA');
    }

    public function test_analise_devolve_resultado_estruturado(): void
    {
        $this->semearCarteira();
        FakeIa::$dados = [
            'resumo' => 'Sua carteira esta concentrada em poucos clientes.',
            'pontos_fortes' => ['Ticket medio saudavel'],
            'alertas' => ['Muitos sumidos'],
            'acoes' => ['Ligar para os em risco'],
        ];

        $this->postJson(route('clientes.carteira.analisar'))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'reaproveitada' => false,
                'resultado' => ['resumo' => 'Sua carteira esta concentrada em poucos clientes.'],
            ]);

        $this->assertSame(StatusAnalise::Ok, AnaliseIa::firstOrFail()->status);
    }

    public function test_segunda_chamada_reaproveita_o_cache(): void
    {
        $this->semearCarteira();

        $this->postJson(route('clientes.carteira.analisar'))->assertOk();
        $this->postJson(route('clientes.carteira.analisar'))
            ->assertOk()
            ->assertJson(['ok' => true, 'reaproveitada' => true]);

        $this->assertSame(1, FakeIa::$chamadas, 'a carteira nao mudou: nao pode chamar o provedor de novo');
    }

    public function test_base_pequena_recusa_sem_gastar_cota(): void
    {
        $this->semearCarteira(2);

        $this->postJson(route('clientes.carteira.analisar'))
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'motivo' => 'sem_dados']);

        $this->assertSame(0, FakeIa::$chamadas);
        $this->assertSame(0, AnaliseIa::count());
    }

    public function test_cota_estourada_devolve_motivo_cota(): void
    {
        $this->semearCarteira();
        Plano::where('slug', Plano::PRO)->update(['limite_tokens_ia_dia' => 100]);

        $this->postJson(route('clientes.carteira.analisar'))->assertOk();

        // Segunda venda muda o hash, entao nao cai no cache — bate na cota.
        $this->semearCarteira(1);

        $this->postJson(route('clientes.carteira.analisar'))
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'motivo' => 'cota']);
    }

    public function test_provedor_fora_do_ar_devolve_motivo_indisponivel(): void
    {
        $this->semearCarteira();
        FakeIa::$falharCom = 'o provedor respondeu 503';

        $this->postJson(route('clientes.carteira.analisar'))
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'motivo' => 'indisponivel']);
    }

    public function test_usuario_sem_permissao_recebe_403(): void
    {
        $this->semearCarteira();

        $comum = $this->criarUsuarioComum($this->contexto['rede'], $this->contexto['empresa']);
        $this->actingAs($comum);
        session(['empresas_atuais' => [$this->contexto['empresa']->id]]);

        $this->postJson(route('clientes.carteira.analisar'))
            ->assertStatus(403)
            ->assertJson(['ok' => false, 'motivo' => 'sem_permissao']);

        $this->assertSame(0, FakeIa::$chamadas);
    }

    public function test_analise_de_outra_rede_nao_aparece_na_tela(): void
    {
        $this->semearCarteira();
        $this->postJson(route('clientes.carteira.analisar'))->assertOk();

        $outra = $this->criarRede('vizinha');
        $this->actingAs($outra['usuario']);
        session(['empresas_atuais' => [$outra['empresa']->id]]);

        $this->get(route('clientes.carteira'))
            ->assertOk()
            ->assertDontSee('Analise de exemplo');
    }
}
