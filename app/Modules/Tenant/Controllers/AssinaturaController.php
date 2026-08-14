<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Actions\{RenovarTrialAction, TransicionarPlanoAction};
use App\Modules\Tenant\Models\{Empresa, Fatura, Plano};
use App\Modules\Tenant\Requests\{RenovarTrialRequest, TransicionarPlanoRequest};
use App\Support\PlanoVigente;
use App\Traits\TratamentoErros;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * "Minha Assinatura": as licencas da rede (uma por unidade), a fatura consolidada do
 * mes e o historico.
 *
 * Nao ha gateway de pagamento — a cobranca e simulada, e a tela diz isso. Faturas
 * tambem nao sao mais fabricadas na leitura da tela: um `GET` nao escreve no banco.
 */
class AssinaturaController extends Controller
{
    use TratamentoErros;

    public function index(): View
    {
        $this->authorize('viewAny', Fatura::class);

        $usuario = auth()->user();
        $rede = $usuario->rede;

        // RedeTrait ja restringe a rede do usuario.
        $licencas = Empresa::with('plano')->orderBy('nome')->get();

        $empresaVigente = PlanoVigente::empresa();
        $plano = $empresaVigente?->plano;

        $cobraveis = $licencas->reject(fn (Empresa $e) => $e->emTrial());
        $valorMensal = (float) $cobraveis->sum(fn (Empresa $e) => (float) $e->plano->preco_por_licenca);

        $referenciaAtual = Carbon::now()->format('Y-m');
        $faturaAtual = Fatura::with('plano:id,nome')
            ->where('referencia', $referenciaAtual)
            ->first();

        $anosDisponiveis = Fatura::pluck('vencimento')
            ->map(fn ($d) => $d->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        $anoSelecionado = (int) request('ano', $anosDisponiveis[0] ?? Carbon::now()->year);

        $faturas = Fatura::with('plano:id,nome')
            ->whereYear('vencimento', $anoSelecionado)
            ->orderByDesc('vencimento')
            ->get();

        $totalPagoNoAno = (float) $faturas->where('status', 'paga')->sum('valor');

        $todosPlanos = Plano::orderBy('preco_por_licenca')->get();
        $podeTrocar = $usuario->can('transicionar', Fatura::class);
        $podeRenovarTeste = $usuario->can('renovarTrial', Fatura::class);

        return view('tenant::assinatura', compact(
            'rede', 'plano', 'empresaVigente', 'licencas', 'valorMensal',
            'faturaAtual', 'faturas', 'anoSelecionado', 'anosDisponiveis', 'totalPagoNoAno',
            'todosPlanos', 'podeTrocar', 'podeRenovarTeste'
        ));
    }

    public function transicionar(TransicionarPlanoRequest $request, TransicionarPlanoAction $action): RedirectResponse
    {
        try {
            // O global scope de rede impede alcançar unidade de outra rede.
            $empresa = Empresa::findOrFail($request->integer('empresa_id'));
            $destino = Plano::findOrFail($request->integer('plano_id'));

            $action->executar($empresa, $destino);

            return redirect()->route('assinatura.index')->with(
                'sucesso',
                "A unidade \"{$empresa->nome}\" passou para o plano \"{$destino->nome}\"."
            );
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Falha ao transicionar plano de assinatura');
        }
    }

    /**
     * Reabre o teste gratuito da unidade por mais um periodo — a saida que o Admin tem,
     * junto com a troca de plano, quando o teste vence (ADR-0013).
     */
    public function renovarTeste(RenovarTrialRequest $request, RenovarTrialAction $action): RedirectResponse
    {
        try {
            // O global scope de rede impede alcançar unidade de outra rede.
            $empresa = Empresa::findOrFail($request->integer('empresa_id'));

            $action->executar($empresa);

            return redirect()->route('assinatura.index')->with(
                'sucesso',
                "O teste da unidade \"{$empresa->nome}\" foi renovado por mais "
                .Empresa::DIAS_DE_TRIAL.' dias, até '
                .$empresa->trial_expira_em->format('d/m/Y').'.'
            );
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Falha ao renovar o teste gratuito');
        }
    }
}
