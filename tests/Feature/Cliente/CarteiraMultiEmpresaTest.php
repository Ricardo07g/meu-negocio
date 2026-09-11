<?php

declare(strict_types=1);

namespace Tests\Feature\Cliente;

use App\Enums\StatusVendaProduto;
use App\Modules\Cliente\Services\SegmentacaoRfmService;
use App\Modules\Ia\Drivers\FakeIa;
use App\Modules\Ia\Models\AnaliseIa;
use App\Modules\Ia\Services\AnaliseService;
use App\Modules\Tenant\Models\{Empresa, Plano};
use App\Modules\Venda\Models\VendaProduto;
use Database\Factories\ClienteFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\{Permission, Role};
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * O eixo multi-empresa da carteira — o que faltava e deixou passar um furo real.
 *
 * A suite original cobria bem o isolamento entre REDES, mas toda rede nascia com uma
 * unidade so, e o unico teste com duas travava `empresa_contexto_atual` de proposito.
 * Justamente o caminho em que o defeito nao aparecia: com duas unidades no header e
 * nenhuma escolhida, a segmentacao agregava as duas enquanto a cota e o carimbo da
 * analise resolviam uma so — a leitura de A+B era gravada e relida como se fosse de A.
 */
class CarteiraMultiEmpresaTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    private array $contexto;

    private Empresa $unidadeB;

    protected function setUp(): void
    {
        parent::setUp();
        FakeIa::resetar();
        $this->contexto = $this->criarRedeAutenticada();
        $this->unidadeB = $this->criarEmpresaExtra($this->contexto['rede']->id, 'Unidade B');

        // `criarRedeAutenticada` deixa a sessao com a unica unidade que existia entao. O
        // `VerificarEmpresa` preserva a selecao do header (so poda o que ficou invalido), logo
        // a unidade nova nao entra sozinha — e sem isso o contexto resolveria 1 empresa e
        // nenhum destes cenarios existiria.
        session(['empresas_atuais' => [$this->contexto['empresa']->id, $this->unidadeB->id]]);
    }

    /** Clientes com compra numa unidade especifica, acima do minimo que a analise exige. */
    private function semearCarteira(int $empresaId, int $clientes = 6, int $base = 100): void
    {
        for ($i = 1; $i <= $clientes; $i++) {
            $cliente = ClienteFactory::new()->create([
                'rede_id' => $this->contexto['rede']->id,
                'nome' => "Cliente {$empresaId}-{$i}",
            ]);

            VendaProduto::create([
                'rede_id' => $this->contexto['rede']->id,
                'empresa_id' => $empresaId,
                'cliente_id' => $cliente->id,
                'usuario_id' => $this->contexto['usuario']->id,
                'data' => now()->subDays(10 * $i)->toDateString(),
                'subtotal' => $base * $i,
                'desconto' => 0,
                'acrescimo' => 0,
                'valor_total' => $base * $i,
                'status' => StatusVendaProduto::Ativa->value,
            ]);
        }
    }

    public function test_com_duas_unidades_e_sem_contexto_a_tela_pede_a_escolha(): void
    {
        $this->semearCarteira($this->contexto['empresa']->id);
        $this->semearCarteira($this->unidadeB->id);

        // Sem `empresa_contexto_atual`: e o estado default de quem tem duas unidades.
        session()->forget('empresa_contexto_atual');

        $this->get(route('clientes.carteira'))
            ->assertOk()
            ->assertSee('Selecione uma unidade para ver a carteira')
            ->assertDontSee('Segmentos');
    }

    public function test_analise_e_recusada_enquanto_a_unidade_nao_for_escolhida(): void
    {
        $this->semearCarteira($this->contexto['empresa']->id);
        $this->semearCarteira($this->unidadeB->id);

        session()->forget('empresa_contexto_atual');

        $this->postJson(route('clientes.carteira.analisar'))
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'motivo' => 'unidade']);

        $this->assertSame(0, FakeIa::$chamadas, 'nao pode chamar o provedor sem unidade definida');
        $this->assertSame(0, AnaliseIa::withoutGlobalScopes()->count(), 'nao pode gravar analise sem unidade');
    }

    public function test_segmentacao_por_unidade_ignora_vendas_da_outra(): void
    {
        $this->semearCarteira($this->contexto['empresa']->id, clientes: 3, base: 100);
        $this->semearCarteira($this->unidadeB->id, clientes: 4, base: 1000);

        // Header com as duas (vem do setUp): sozinho, o global scope agregaria A+B.
        session()->forget('empresa_contexto_atual');

        $carteira = app(SegmentacaoRfmService::class)->segmentar($this->contexto['empresa']->id);

        $this->assertSame(3, $carteira['clientes_com_compra'], 'so os clientes que compraram na unidade A');
        $this->assertSame(600.0, $carteira['receita_total'], '100 + 200 + 300, sem nada da unidade B');
    }

    public function test_consumo_de_uma_unidade_nao_reduz_a_franquia_da_outra(): void
    {
        $this->semearCarteira($this->contexto['empresa']->id);

        session(['empresa_contexto_atual' => $this->contexto['empresa']->id]);

        $this->postJson(route('clientes.carteira.analisar'))->assertOk();

        $analises = app(AnaliseService::class);

        $this->assertSame(1, $analises->analisesDoDia($this->contexto['empresa']->id));
        $this->assertSame(0, $analises->analisesDoDia($this->unidadeB->id), 'a unidade B nao gastou nada');
        $this->assertSame(10, $analises->restanteDoDia($this->unidadeB->id), 'a franquia da B segue inteira');
    }

    public function test_unidade_no_gratis_nao_analisa_mesmo_com_irma_no_pro(): void
    {
        $gratis = Plano::where('slug', Plano::GRATIS)->firstOrFail();
        $this->unidadeB->update(['plano_id' => $gratis->id]);

        $this->semearCarteira($this->unidadeB->id);

        // Operando na unidade B (Gratis), enquanto a A da mesma rede segue no Pro.
        session(['empresa_contexto_atual' => $this->unidadeB->id]);

        $this->postJson(route('clientes.carteira.analisar'))
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'motivo' => 'cota']);

        $this->assertSame(0, FakeIa::$chamadas, 'licenca sem IA nao pode gastar a franquia da unidade vizinha');
    }

    public function test_sem_ia_ver_a_tela_mostra_a_segmentacao_mas_nao_o_texto_da_analise(): void
    {
        $this->semearCarteira($this->contexto['empresa']->id);
        session(['empresa_contexto_atual' => $this->contexto['empresa']->id]);

        $this->postJson(route('clientes.carteira.analisar'))->assertOk();

        // Perfil que enxerga clientes mas nao tem `ia.ver`: le a segmentacao, que e SQL,
        // e nao o texto do modelo (ADR-0021 separa consultar de mandar rodar).
        $this->garantirRole('Recepcao');
        Role::where('name', 'Recepcao')->firstOrFail()->syncPermissions([
            Permission::firstOrCreate(['name' => 'cliente.ver', 'guard_name' => 'web']),
        ]);

        $recepcao = $this->criarUsuarioComum($this->contexto['rede'], $this->contexto['empresa'], 'Recepcao');
        $this->actingAs($recepcao);
        session(['empresa_contexto_atual' => $this->contexto['empresa']->id]);

        $this->get(route('clientes.carteira'))
            ->assertOk()
            ->assertSee('Segmentos')
            ->assertDontSee('Analise de exemplo');
    }
}
