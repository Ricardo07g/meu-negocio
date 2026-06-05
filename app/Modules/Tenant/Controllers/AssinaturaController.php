<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Actions\TransicionarPlanoAction;
use App\Modules\Tenant\Models\Fatura;
use App\Modules\Tenant\Models\Plano;
use App\Modules\Tenant\Models\Rede;
use App\Modules\Tenant\Requests\TransicionarPlanoRequest;
use App\Modules\Usuario\Models\Usuario;
use App\Traits\TratamentoErros;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AssinaturaController extends Controller
{
    use TratamentoErros;

    public function index(): View
    {
        $this->authorize('viewAny', Fatura::class);

        $usuario = auth()->user();
        $rede = $usuario->rede->loadMissing('plano');
        $plano = $rede->plano;

        $usoEmpresas = $rede->empresas()->count();
        $usoUsuarios = $rede->usuarios()->count();

        $this->garantirHistoricoFaturas($rede);

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

        $todosPlanos = Plano::orderBy('preco_mensal')->get();
        $podeTrocar = $usuario->can('transicionar', Fatura::class);

        return view('tenant::assinatura', compact(
            'rede', 'plano', 'usoEmpresas', 'usoUsuarios',
            'faturaAtual', 'faturas', 'anoSelecionado', 'anosDisponiveis', 'totalPagoNoAno',
            'todosPlanos', 'podeTrocar'
        ));
    }

    public function transicionar(TransicionarPlanoRequest $request, TransicionarPlanoAction $action): RedirectResponse
    {
        try {
            /** @var Usuario $usuario */
            $usuario = auth()->user();
            /** @var Rede $rede */
            $rede = $usuario->rede;
            $destino = Plano::findOrFail($request->integer('plano_id'));
            $fatura = $action->executar($rede, $destino);

            return redirect()->route('assinatura.index')->with(
                'sucesso',
                "Plano alterado para \"{$destino->nome}\". Fatura do mes ajustada para R$ "
                .number_format((float) $fatura->valor, 2, ',', '.').'.'
            );
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Falha ao transicionar plano de assinatura');
        }
    }

    /**
     * Cria, sob demanda, faturas mensais entre a criacao da rede e o mes atual.
     * Faturas de meses passados sao marcadas como "paga"; a do mes vigente
     * fica em aberto (ou paga se o vencimento ja passou). E idempotente por
     * causa do unique (rede_id, referencia).
     */
    private function garantirHistoricoFaturas(Rede $rede): void
    {
        if (! $rede->created_at || ! $rede->plano) {
            return;
        }

        $cursor = $rede->created_at->copy()->startOfMonth();
        $fim = Carbon::now()->startOfMonth();
        $iteracoes = 0;

        while ($cursor->lte($fim) && $iteracoes < 60) {
            $referencia = $cursor->format('Y-m');
            $existe = Fatura::where('referencia', $referencia)->exists();

            if (! $existe) {
                $diaCriacao = $rede->created_at->day;
                $diaVencimento = min($diaCriacao, $cursor->copy()->endOfMonth()->day);
                $vencimento = Carbon::create($cursor->year, $cursor->month, $diaVencimento);
                $eMesAtual = $referencia === $fim->format('Y-m');

                $status = 'em_aberto';
                $pagoEm = null;

                if (! $eMesAtual) {
                    $status = 'paga';
                    $pagoEm = $vencimento->copy()->addDays(rand(0, 4));
                } elseif ($vencimento->isPast()) {
                    $status = 'paga';
                    $pagoEm = $vencimento->copy()->addDays(rand(0, 2));
                }

                Fatura::create([
                    'rede_id' => $rede->id,
                    'plano_id' => $rede->plano_id,
                    'referencia' => $referencia,
                    'valor' => $rede->plano->preco_mensal,
                    'vencimento' => $vencimento,
                    'pago_em' => $pagoEm,
                    'status' => $status,
                ]);
            }

            $cursor->addMonth();
            $iteracoes++;
        }
    }
}
