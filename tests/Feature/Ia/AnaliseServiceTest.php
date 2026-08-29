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

    /**
     * Regressao: o hash cobria dados+versao+modelo, mas nao o texto do prompt — editar as
     * instrucoes sem lembrar de bumpar `versaoPrompt` a mao servia resultado velho em silencio.
     * E o mesmo "alguem vai esquecer" que motivou nao invalidar cache por evento de venda.
     */
    public function test_mudar_o_texto_do_prompt_invalida_o_cache(): void
    {
        ['empresa' => $empresa] = $this->criarRedeAutenticada();
        $service = app(AnaliseService::class);
        $dados = ['segmentos' => ['campeao' => 3]];

        $service->analisar($empresa, TipoAnalise::CarteiraRfm, new PedidoIa(
            instrucoes: 'Seja breve.', dados: $dados, schema: ['type' => 'object'],
        ));
        $service->analisar($empresa, TipoAnalise::CarteiraRfm, new PedidoIa(
            instrucoes: 'Seja breve e cite os numeros.', dados: $dados, schema: ['type' => 'object'],
        ));

        $this->assertSame(2, FakeIa::$chamadas, 'prompt diferente e analise diferente');
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
        // Franquia de uma analise: a primeira passa, a segunda bate no teto.
        Plano::where('slug', Plano::PRO)->update(['limite_analises_ia_dia' => 1]);

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
        $this->assertSame(0, app(AnaliseService::class)->analisesDoDia());
    }

    public function test_franquia_do_dia_conta_as_analises_realizadas(): void
    {
        ['empresa' => $empresa] = $this->criarRedeAutenticada();
        $service = app(AnaliseService::class);

        $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido(['v' => 1]));
        $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido(['v' => 2]));

        $this->assertSame(2, $service->analisesDoDia());
        $this->assertSame(8, $service->restanteDoDia(), 'Pro da 10 analises por dia');
    }

    /**
     * A propriedade que torna o botao seguro de clicar: reanalisar uma carteira que nao
     * mudou nao gasta a franquia do dia, porque o cache hit nao cria linha nova.
     */
    public function test_cache_hit_nao_consome_franquia(): void
    {
        ['empresa' => $empresa] = $this->criarRedeAutenticada();
        $service = app(AnaliseService::class);

        $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido());
        $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido());
        $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido());

        $this->assertSame(1, $service->analisesDoDia(), 'tres cliques, uma analise gasta');
        $this->assertSame(9, $service->restanteDoDia());
    }

    /** Falha do provedor e problema nosso: nao pode descontar da franquia do lojista. */
    public function test_erro_do_provedor_nao_consome_franquia(): void
    {
        ['empresa' => $empresa] = $this->criarRedeAutenticada();
        $service = app(AnaliseService::class);
        FakeIa::$falharCom = 'timeout';

        try {
            $service->analisar($empresa, TipoAnalise::CarteiraRfm, $this->pedido());
        } catch (IaIndisponivelException) {
            // esperado
        }

        $this->assertSame(1, AnaliseIa::count(), 'o erro fica registrado para medicao');
        $this->assertSame(0, $service->analisesDoDia(), 'mas nao desconta da franquia');
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
