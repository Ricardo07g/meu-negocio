<?php

declare(strict_types=1);

namespace Tests\Feature\Ia;

use App\Exceptions\PlanoLimiteException;
use App\Modules\Ia\Drivers\FakeIa;
use App\Modules\Ia\DTOs\PedidoIa;
use App\Modules\Ia\Enums\{StatusAnalise, TipoAnalise};
use App\Modules\Ia\Exceptions\IaIndisponivelException;
use App\Modules\Ia\Models\AnaliseIa;
use App\Modules\Ia\Services\AnaliseService;
use App\Modules\Tenant\Models\{Empresa, Plano};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * O nucleo de IA: cache por hash, cota diaria por empresa e isolamento.
 *
 * A suite roda com IA_DRIVER=fake (phpunit.xml) — nenhum teste toca a rede. O contador
 * `FakeIa::$chamadas` e o que prova que o cache economizou de verdade, em vez de so
 * devolver a resposta certa.
 */
class AnaliseServiceTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FakeIa::resetar();
    }

    private function pedido(array $dados = ['segmentos' => ['campeao' => 3]]): PedidoIa
    {
        return new PedidoIa(
            instrucoes: 'Analise a carteira.',
            dados: $dados,
            schema: ['type' => 'object', 'properties' => ['resumo' => ['type' => 'string']]],
        );
    }

    public function test_analise_registra_tokens_custo_e_resultado(): void
    {
        ['empresa' => $empresa] = $this->criarRedeAutenticada();

        $analise = app(AnaliseService::class)
            ->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido());

        $this->assertSame(StatusAnalise::Ok, $analise->status);
        $this->assertSame(150, $analise->tokensTotais());
        $this->assertSame('fake', $analise->modelo);
        $this->assertSame($empresa->id, $analise->analisavel_id);
        $this->assertArrayHasKey('resumo', $analise->resultado);
        $this->assertSame(1, FakeIa::$chamadas);
    }

    public function test_payload_igual_reaproveita_o_cache_sem_chamar_o_provedor(): void
    {
        ['empresa' => $empresa] = $this->criarRedeAutenticada();
        $service = app(AnaliseService::class);

        $primeira = $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido());
        $segunda = $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido());

        $this->assertSame($primeira->id, $segunda->id, 'deveria devolver a MESMA analise');
        $this->assertSame(1, FakeIa::$chamadas, 'o provedor nao pode ser chamado de novo');
        $this->assertSame(1, $segunda->reaproveitacoes);
        $this->assertNotNull($segunda->ultima_reaproveitacao_em);
        $this->assertSame(1, AnaliseIa::count());
    }

    public function test_payload_diferente_gera_nova_analise(): void
    {
        ['empresa' => $empresa] = $this->criarRedeAutenticada();
        $service = app(AnaliseService::class);

        $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido(['segmentos' => ['campeao' => 3]]));
        $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido(['segmentos' => ['campeao' => 9]]));

        $this->assertSame(2, FakeIa::$chamadas);
        $this->assertSame(2, AnaliseIa::count());
    }

    public function test_cota_estourada_recusa_registra_e_nao_chama_o_provedor(): void
    {
        ['empresa' => $empresa] = $this->criarRedeAutenticada();
        // Cota menor que o consumo de uma chamada: a primeira passa, a segunda bate no teto.
        Plano::where('slug', Plano::PRO)->update(['limite_tokens_ia_dia' => 100]);

        $service = app(AnaliseService::class);
        $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido(['v' => 1]));

        try {
            $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido(['v' => 2]));
            $this->fail('deveria ter recusado por cota');
        } catch (PlanoLimiteException $e) {
            $this->assertStringContainsString('analises por IA no dia', $e->getMessage());
        }

        $this->assertSame(1, FakeIa::$chamadas, 'a recusa nao pode gastar chamada');
        $this->assertSame(
            1,
            AnaliseIa::where('status', StatusAnalise::RecusadoCota->value)->count(),
            'a recusa precisa virar linha, senao "quantas vezes batemos no teto" fica invisivel'
        );
    }

    public function test_plano_sem_cota_deixa_a_feature_indisponivel(): void
    {
        ['empresa' => $empresa] = $this->criarRedeAutenticada();
        $empresa->update(['plano_id' => Plano::where('slug', Plano::GRATIS)->firstOrFail()->id]);

        $service = app(AnaliseService::class);

        $this->assertFalse($service->disponivel());
        $this->assertSame(0, $service->limiteDoDia());

        $this->expectException(PlanoLimiteException::class);
        $service->analisar($empresa->fresh(), TipoAnalise::CarteiraRfm, $this->pedido());
    }

    public function test_provedor_desligado_nao_registra_e_avisa(): void
    {
        ['empresa' => $empresa] = $this->criarRedeAutenticada();
        FakeIa::$ativo = false;

        $this->expectException(IaIndisponivelException::class);

        try {
            app(AnaliseService::class)->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido());
        } finally {
            $this->assertSame(0, AnaliseIa::count(), 'provedor desligado nao consome cota nem gera linha');
        }
    }

    public function test_falha_do_provedor_registra_erro_e_propaga(): void
    {
        ['empresa' => $empresa] = $this->criarRedeAutenticada();
        FakeIa::$falharCom = 'o provedor respondeu 503';

        try {
            app(AnaliseService::class)->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido());
            $this->fail('deveria ter propagado a indisponibilidade');
        } catch (IaIndisponivelException $e) {
            $this->assertStringContainsString('503', $e->getMessage());
        }

        $analise = AnaliseIa::firstOrFail();
        $this->assertSame(StatusAnalise::Erro, $analise->status);
        $this->assertStringContainsString('503', (string) $analise->erro);
        $this->assertNull($analise->resultado);
    }

    public function test_erro_nao_e_reaproveitado_como_cache(): void
    {
        ['empresa' => $empresa] = $this->criarRedeAutenticada();
        $service = app(AnaliseService::class);

        FakeIa::$falharCom = 'timeout';
        try {
            $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido());
        } catch (IaIndisponivelException) {
            // esperado
        }

        FakeIa::$falharCom = null;
        $analise = $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido());

        $this->assertSame(StatusAnalise::Ok, $analise->status, 'uma falha nao pode virar cache');
        $this->assertSame(2, FakeIa::$chamadas);
    }

    public function test_rede_nao_enxerga_analise_de_outra_rede(): void
    {
        ['empresa' => $empresaA] = $this->criarRedeAutenticada();
        app(AnaliseService::class)->analisar($empresaA, TipoAnalise::CarteiraRfm, $this->pedido());

        $outra = $this->criarRede('outra');
        $this->actingAs($outra['usuario']);
        session(['empresas_atuais' => [$outra['empresa']->id]]);

        $this->assertSame(0, AnaliseIa::count(), 'analise de outra rede nao pode vazar');
        $this->assertSame(0, app(AnaliseService::class)->consumoDoDia());
    }

    public function test_consumo_do_dia_soma_entrada_e_saida_da_empresa(): void
    {
        ['empresa' => $empresa] = $this->criarRedeAutenticada();
        $service = app(AnaliseService::class);

        $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido(['v' => 1]));
        $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido(['v' => 2]));

        $this->assertSame(300, $service->consumoDoDia());
        $this->assertSame(50_000 - 300, $service->restanteDoDia());
    }

    public function test_cache_expira_apos_a_janela_de_seguranca(): void
    {
        ['empresa' => $empresa] = $this->criarRedeAutenticada();
        $service = app(AnaliseService::class);

        $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido());
        AnaliseIa::query()->update(['created_at' => now()->subDays((int) config('ia.cache_dias') + 1)]);

        $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido());

        $this->assertSame(2, FakeIa::$chamadas, 'analise velha demais deve ser refeita');
    }
}
