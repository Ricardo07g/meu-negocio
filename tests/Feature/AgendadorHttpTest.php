<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\{FormatoExportacao, StatusExportacao};
use App\Modules\Conta\Models\{Conta, Exportacao};
use App\Support\ExecutarAgendadosCatchUp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Cache, Storage};
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Agendador por HTTP (ADR-0016): em producao nao ha processo `schedule:work`,
 * quem faz o papel de relogio e um Cron Trigger do Cloudflare que chama
 * POST /cron/executar.
 *
 * Dois eixos cobertos aqui: a **porta** (o endpoint so abre com o token certo,
 * e some com 404 quando nao abre) e o **catch-up** (roda o que ficou devido
 * desde o ultimo tick, sem repetir no ping seguinte).
 */
class AgendadorHttpTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-de-teste-abcdef';

    /** Momento fixo em todos os testes: 10:30 UTC, com o dia ja bem entrado. */
    private const AGORA = '2026-08-08 10:30:00';

    protected function setUp(): void
    {
        parent::setUp();

        config(['cron.token' => self::TOKEN]);
        Storage::fake('r2');
        config(['arquivos.disco' => 'r2']);
        $this->travelTo(Carbon::parse(self::AGORA));
    }

    // ------------------------------------------------------------------
    // Porta: autenticacao por token
    // ------------------------------------------------------------------

    public function test_sem_token_responde_404(): void
    {
        $this->postJson(route('cron.executar'))->assertNotFound();
    }

    public function test_token_errado_responde_404(): void
    {
        $this->acionar('token-errado')->assertNotFound();
    }

    public function test_token_nao_configurado_fecha_o_endpoint(): void
    {
        config(['cron.token' => null]);

        // Mesmo mandando um header vazio (que "bateria" com a config vazia).
        $this->acionar('')->assertNotFound();
        $this->acionar(self::TOKEN)->assertNotFound();
    }

    public function test_token_valido_responde_200_com_o_resumo(): void
    {
        $this->acionar()
            ->assertOk()
            ->assertJsonStructure(['ultimo_tick', 'tick', 'executados']);
    }

    // ------------------------------------------------------------------
    // Catch-up: o que ficou devido desde o ultimo tick
    // ------------------------------------------------------------------

    public function test_sem_tick_anterior_roda_tudo_que_ficou_devido_na_janela(): void
    {
        // Sem tick, a janela padrao (24h) cobre a virada do dia: as diarias
        // (0 0 * * *) e a horaria (0 * * * *) ficaram devidas.
        $this->assertSame(
            ['arquivos:limpar-rascunhos', 'exportacoes:limpar', 'assinaturas:expirar-trial'],
            $this->comandosExecutados($this->acionar()),
        );
    }

    public function test_tick_recente_no_mesmo_dia_roda_so_a_tarefa_horaria(): void
    {
        // Tick as 09:30: das 09:30 as 10:30 so passou o "0 * * * *" das 10:00.
        Cache::forever(ExecutarAgendadosCatchUp::CHAVE_ULTIMO_TICK, '2026-08-08T09:30:00+00:00');

        $this->assertSame(
            ['exportacoes:limpar'],
            $this->comandosExecutados($this->acionar()),
        );
    }

    public function test_nada_ficou_devido_desde_o_ultimo_tick(): void
    {
        // Tick as 10:05: nenhuma expressao passa entre 10:05 e 10:30.
        Cache::forever(ExecutarAgendadosCatchUp::CHAVE_ULTIMO_TICK, '2026-08-08T10:05:00+00:00');

        $this->assertSame([], $this->comandosExecutados($this->acionar()));
    }

    public function test_ping_seguinte_nao_repete_as_tarefas(): void
    {
        $this->assertNotEmpty($this->comandosExecutados($this->acionar()));

        // Segundo ping logo em seguida: o tick foi gravado, nada mais venceu.
        $this->assertSame([], $this->comandosExecutados($this->acionar()));
    }

    public function test_tick_e_gravado_para_o_proximo_ping(): void
    {
        $this->acionar()->assertOk();

        $this->assertSame(
            Carbon::parse(self::AGORA)->toIso8601String(),
            Cache::get(ExecutarAgendadosCatchUp::CHAVE_ULTIMO_TICK),
        );
    }

    // ------------------------------------------------------------------
    // Efeito real: a tarefa roda de verdade, nao so aparece na lista
    // ------------------------------------------------------------------

    public function test_a_chamada_remove_exportacao_expirada_e_o_arquivo(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $conta = Conta::where('empresa_id', $ctx['empresa']->id)->firstOrFail();

        $caminho = 'exportacoes/extrato-vencido.csv';
        Storage::disk('r2')->put($caminho, 'id;valor');

        $exportacao = Exportacao::create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'conta_id' => $conta->id,
            'usuario_id' => $ctx['usuario']->id,
            'formato' => FormatoExportacao::Csv,
            'periodo_inicio' => '2026-07-01',
            'periodo_fim' => '2026-07-31',
            'status' => StatusExportacao::Pronto,
            'disco' => 'r2',
            'caminho' => $caminho,
            'nome_arquivo' => 'extrato.csv',
        ]);

        // Alem da retencao (Exportacao::DIAS_RETENCAO = 1 dia).
        $exportacao->forceFill(['created_at' => Carbon::parse(self::AGORA)->subDays(3)])->saveQuietly();

        // A sessao autenticada acima nao pode influenciar: o endpoint roda sem auth.
        $this->flushSession();

        $this->acionar()->assertOk();

        $this->assertDatabaseMissing('exportacoes', ['id' => $exportacao->id]);
        Storage::disk('r2')->assertMissing($caminho);
    }

    // ------------------------------------------------------------------

    private function acionar(?string $token = self::TOKEN): TestResponse
    {
        return $this->postJson(
            route('cron.executar'),
            [],
            $token === null ? [] : ['X-Cron-Token' => $token],
        );
    }

    /**
     * @return list<string>
     */
    private function comandosExecutados(TestResponse $resposta): array
    {
        $resposta->assertOk();

        return array_column($resposta->json('executados'), 'comando');
    }
}
