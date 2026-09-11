<?php

declare(strict_types=1);

namespace App\Modules\Cliente\Controllers;

use App\Exceptions\PlanoLimiteException;
use App\Http\Controllers\Controller;
use App\Modules\Cliente\Actions\AnalisarCarteiraAction;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Cliente\Services\SegmentacaoRfmService;
use App\Modules\Ia\Enums\{StatusAnalise, TipoAnalise};
use App\Modules\Ia\Exceptions\{DadosInsuficientesException, IaIndisponivelException};
use App\Modules\Ia\Models\AnaliseIa;
use App\Modules\Ia\Services\AnaliseService;
use App\Modules\Tenant\Models\Empresa;
use App\Support\ContextoEmpresa;
use App\Traits\TratamentoErros;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\{JsonResponse, RedirectResponse};
use Illuminate\View\View;

/**
 * Carteira de clientes segmentada por RFM.
 *
 * A tela e util **sem IA nenhuma** — a segmentacao e SQL. A analise por IA e um botao
 * opcional em cima disso; quando o provedor esta desligado, sem cota ou fora do ar, a
 * pagina continua entregando o mesmo valor, so sem o texto interpretativo.
 */
class CarteiraController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private readonly SegmentacaoRfmService $rfm,
        private readonly AnaliseService $analises,
    ) {}

    public function index(): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Cliente::class);

            $empresaId = ContextoEmpresa::resolver();

            // Sem UMA unidade resolvida a carteira nao tem recorte: o global scope agregaria
            // todas as unidades do header enquanto a cota e o carimbo da analise resolvem uma
            // so. Em vez de escolher no lugar do usuario, a tela pede a escolha — mesmo
            // empty state do Caixa Diario, que ja opera por unidade unica (ME-010 v3).
            if ($empresaId === null) {
                // `podeVerIa`/`iaDisponivel` vao mesmo sem uso no empty state: o push de JS da
                // tela mora FORA do `@section`, entao o `@php return; @endphp` nao o alcanca.
                return view('cliente::carteira', [
                    'precisaEscolherUnidade' => true,
                    'podeVerIa' => false,
                    'iaDisponivel' => false,
                ]);
            }

            // Ler a analise guardada nao e o mesmo que mandar rodar outra (ADR-0021): quem
            // nao tem `ia.ver` enxerga a segmentacao, que e SQL, mas nao o texto do modelo.
            $podeVerIa = auth()->user()?->can('viewAny', AnaliseIa::class) ?? false;

            $carteira = $this->rfm->segmentar($empresaId);
            $ultima = $podeVerIa ? $this->ultimaAnalise($empresaId) : null;

            return view('cliente::carteira', [
                'precisaEscolherUnidade' => false,
                'carteira' => $carteira,
                'podeVerIa' => $podeVerIa,
                'iaDisponivel' => $this->analises->disponivel($empresaId),
                'iaAnalisesHoje' => $this->analises->analisesDoDia($empresaId),
                'iaLimite' => $this->analises->limiteDoDia($empresaId),
                'ultimaAnalise' => $ultima,
                'analiseDesatualizada' => $this->desatualizada($ultima, $carteira),
                'minimoClientes' => AnalisarCarteiraAction::MINIMO_CLIENTES,
            ]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar a carteira de clientes');
        }
    }

    /**
     * Endpoint AJAX da analise.
     *
     * Devolve sempre JSON — inclusive no erro — com um `motivo` estavel, para a tela poder
     * dizer o que aconteceu em vez de mostrar "algo deu errado". Sao quatro caminhos
     * distintos e cada um pede uma mensagem diferente do usuario.
     */
    public function analisar(AnalisarCarteiraAction $analisar): JsonResponse
    {
        try {
            $this->authorize('create', AnaliseIa::class);

            // Nao usa o fallback de `PlanoVigente` (empresa default do usuario) de proposito:
            // ele resolvia UMA unidade enquanto a segmentacao lia varias, e a analise nascia
            // carimbada numa empresa descrevendo a carteira de outras.
            $empresaId = ContextoEmpresa::resolver();
            $empresa = $empresaId === null ? null : Empresa::with('plano')->find($empresaId);

            if ($empresa === null) {
                return $this->recusar('unidade', 'Selecione uma unidade para analisar a carteira.');
            }

            $analise = $analisar->executar($empresa);

            return response()->json([
                'ok' => true,
                'resultado' => $analise->resultado,
                'reaproveitada' => $analise->reaproveitacoes > 0,
                'geradaEm' => $analise->created_at?->format('d/m/Y H:i'),
                'analisesHoje' => $this->analises->analisesDoDia($empresaId),
                'limite' => $this->analises->limiteDoDia($empresaId),
            ]);
        } catch (DadosInsuficientesException $e) {
            return $this->recusar('sem_dados', $e->getMessage());
        } catch (PlanoLimiteException $e) {
            return $this->recusar('cota', $e->getMessage());
        } catch (IaIndisponivelException $e) {
            return $this->recusar('indisponivel', $e->getMessage());
        } catch (AuthorizationException) {
            return $this->recusar('sem_permissao', 'Voce nao tem permissao para gerar analises.', 403);
        } catch (\Throwable $e) {
            report($e);

            return $this->recusar('indisponivel', 'Nao foi possivel gerar a analise agora.');
        }
    }

    /**
     * A analise guardada ainda corresponde a carteira de hoje?
     *
     * Compara o hash do pedido atual com o da ultima analise. Sem esse aviso o usuario ve um
     * texto antigo sem pista nenhuma de que ele envelheceu — e nao entende por que um clique
     * volta instantaneo e outro demora dez segundos.
     */
    private function desatualizada(?AnaliseIa $ultima, array $carteira): bool
    {
        if ($ultima === null) {
            return false;
        }

        $pedido = app(AnalisarCarteiraAction::class)->montarPedido($carteira);

        return $ultima->hash_entrada !== $this->analises->hashDoPedido($pedido);
    }

    private function recusar(string $motivo, string $mensagem, int $status = 422): JsonResponse
    {
        $empresaId = ContextoEmpresa::resolver();

        return response()->json([
            'ok' => false,
            'motivo' => $motivo,
            'mensagem' => $mensagem,
            'analisesHoje' => $empresaId === null ? 0 : $this->analises->analisesDoDia($empresaId),
            'limite' => $empresaId === null ? 0 : $this->analises->limiteDoDia($empresaId),
        ], $status);
    }

    /** Ultima analise da unidade, para a tela abrir ja com o texto anterior em vez de vazia. */
    private function ultimaAnalise(int $empresaId): ?AnaliseIa
    {
        return AnaliseIa::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', TipoAnalise::CarteiraRfm->value)
            ->where('status', StatusAnalise::Ok->value)
            ->latest('created_at')
            ->first();
    }
}
