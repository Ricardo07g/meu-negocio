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
use App\Support\PlanoVigente;
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

            $carteira = $this->rfm->segmentar();
            $ultima = $this->ultimaAnalise();

            return view('cliente::carteira', [
                'carteira' => $carteira,
                'iaDisponivel' => $this->analises->disponivel(),
                'iaAnalisesHoje' => $this->analises->analisesDoDia(),
                'iaLimite' => $this->analises->limiteDoDia(),
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

            $empresa = PlanoVigente::empresa();

            if ($empresa === null) {
                return $this->recusar('desligado', 'Selecione uma unidade para analisar a carteira.');
            }

            $analise = $analisar->executar($empresa);

            return response()->json([
                'ok' => true,
                'resultado' => $analise->resultado,
                'reaproveitada' => $analise->reaproveitacoes > 0,
                'geradaEm' => $analise->created_at?->format('d/m/Y H:i'),
                'analisesHoje' => $this->analises->analisesDoDia(),
                'limite' => $this->analises->limiteDoDia(),
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
        return response()->json([
            'ok' => false,
            'motivo' => $motivo,
            'mensagem' => $mensagem,
            'analisesHoje' => $this->analises->analisesDoDia(),
            'limite' => $this->analises->limiteDoDia(),
        ], $status);
    }

    /** Ultima analise da unidade, para a tela abrir ja com o texto anterior em vez de vazia. */
    private function ultimaAnalise(): ?AnaliseIa
    {
        $empresaId = PlanoVigente::empresaId();

        if ($empresaId === null) {
            return null;
        }

        return AnaliseIa::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', TipoAnalise::CarteiraRfm->value)
            ->where('status', StatusAnalise::Ok->value)
            ->latest('created_at')
            ->first();
    }
}
