<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Controllers;

use App\Enums\StatusFatura;
use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Modules\Tenant\Actions\TransicionarPlanoAction;
use App\Modules\Tenant\Models\{Fatura, Plano, Rede};
use App\Modules\Tenant\Requests\TransicionarPlanoRequest;
use App\Modules\Tenant\Services\PlanoService;
use App\Modules\Tenant\Support\CalculadoraProRata;
use App\Modules\Usuario\Models\Usuario;
use App\Traits\TratamentoErros;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AssinaturaController extends Controller
{
    use TratamentoErros;

    public function __construct(private readonly PlanoService $planoService) {}

    public function index(): View
    {
        $this->authorize('viewAny', Fatura::class);

        /** @var Usuario $usuario */
        $usuario = auth()->user();
        /** @var Rede $rede */
        $rede = $usuario->rede;

        // Aplica um eventual downgrade agendado ANTES de ler o plano vigente,
        // para que a tela e a fatura do mes ja reflitam o plano novo na virada.
        $this->aplicarPlanoAgendadoSeViravelMes($rede);

        $rede->load(['plano', 'planoAgendado']);
        $plano = $rede->plano;

        $usoEmpresas = $rede->empresas()->count();
        $usoUsuarios = $rede->usuariosAtivos()->count();

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

        $totalPagoNoAno = (float) $faturas->where('status', StatusFatura::Paga)->sum('valor');

        $todosPlanos = Plano::orderBy('preco_mensal')->get();
        $podeTrocar = $usuario->can('transicionar', Fatura::class);
        $previas = $this->montarPrevias($rede, $todosPlanos, $plano, $faturaAtual);

        return view('tenant::assinatura', compact(
            'rede', 'plano', 'usoEmpresas', 'usoUsuarios',
            'faturaAtual', 'faturas', 'anoSelecionado', 'anosDisponiveis', 'totalPagoNoAno',
            'todosPlanos', 'podeTrocar', 'previas'
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
            $resultado = $action->executar($rede, $destino);

            return redirect()->route('assinatura.index')->with('sucesso', $resultado->mensagem());
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Falha ao transicionar plano de assinatura');
        }
    }

    public function pagar(Fatura $fatura): RedirectResponse
    {
        $this->authorize('pagar', $fatura);

        try {
            if (! in_array($fatura->status, [StatusFatura::EmAberto, StatusFatura::Vencida], true)) {
                throw new NegocioException('Esta fatura nao esta em aberto.');
            }

            $fatura->update([
                'status' => StatusFatura::Paga,
                'pago_em' => Carbon::now(),
            ]);

            return redirect()->route('assinatura.index')->with('sucesso', 'Fatura marcada como paga.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Falha ao marcar fatura como paga');
        }
    }

    /**
     * Monta a previa do efeito de trocar para cada plano (ADR-0008), para o
     * modal exibir antes de confirmar: upgrade com ajuste pro-rata, upgrade sem
     * ajuste (fatura ja paga) ou downgrade agendado para o proximo ciclo.
     *
     * @param  Collection<int, Plano>  $planos
     * @return array<int, array{tipo: string, texto: string}>
     */
    private function montarPrevias(Rede $rede, $planos, Plano $atual, ?Fatura $faturaAtual): array
    {
        $previas = [];
        $faturaEmAberto = $faturaAtual === null || $faturaAtual->status === StatusFatura::EmAberto;
        $proximoCiclo = Carbon::now()->addMonthNoOverflow()->startOfMonth();
        $usoEmpresas = $rede->empresas()->count();
        $usoUsuarios = $rede->usuariosAtivos()->count();

        foreach ($planos as $p) {
            if ($p->id === $atual->id) {
                continue;
            }

            $precoNovo = (float) $p->preco_mensal;
            $precoNovoLabel = $precoNovo > 0 ? 'R$ '.number_format($precoNovo, 2, ',', '.').'/mes' : 'Gratuito';

            if ($precoNovo < (float) $atual->preco_mensal) {
                // Downgrade: so e agendavel se o uso atual couber no plano destino.
                if (! $this->planoService->cabeNoPlano($rede, $p)) {
                    $limiteEmp = $p->max_empresas > 0 ? (string) $p->max_empresas : 'ilimitado';
                    $limiteUsr = $p->max_usuarios > 0 ? (string) $p->max_usuarios : 'ilimitado';
                    $previas[$p->id] = [
                        'tipo' => 'downgrade_bloqueado',
                        'texto' => "Seu uso atual ({$usoEmpresas} empresa(s) / {$usoUsuarios} usuario(s)) excede os "
                            ."limites deste plano ({$limiteEmp} empresa(s) / {$limiteUsr} usuario(s)). "
                            .'Reduza antes de poder agendar.',
                    ];

                    continue;
                }

                $previas[$p->id] = [
                    'tipo' => 'downgrade',
                    'texto' => 'Passa a valer em '.$proximoCiclo->format('d/m/Y').", custando {$precoNovoLabel}. "
                        .'Voce mantem o plano atual ate la, sem reembolso.',
                ];

                continue;
            }

            if (! $faturaEmAberto) {
                $previas[$p->id] = [
                    'tipo' => 'upgrade_sem_ajuste',
                    'texto' => 'Efeito imediato. A fatura deste mes nao muda (ja quitada); '
                        ."o novo valor ({$precoNovoLabel}) passa a valer na proxima fatura.",
                ];

                continue;
            }

            $proRata = CalculadoraProRata::calcular((float) $atual->preco_mensal, $precoNovo);
            $previas[$p->id] = [
                'tipo' => 'upgrade',
                'texto' => 'Efeito imediato. A fatura deste mes sera ajustada pro-rata para R$ '
                    .number_format($proRata, 2, ',', '.').'.',
            ];
        }

        return $previas;
    }

    /**
     * Na virada do mes, aplica um downgrade que estava agendado: troca o plano
     * vigente e gera a fatura do novo mes ja no plano novo. Se, nesse meio-tempo,
     * a rede passou a exceder os limites do destino, cancela o agendamento.
     * Disparado de forma lazy ao abrir a tela (o projeto nao tem scheduler).
     */
    private function aplicarPlanoAgendadoSeViravelMes(Rede $rede): void
    {
        if ($rede->plano_agendado_id === null) {
            return;
        }

        $ultimaReferencia = Fatura::max('referencia');
        $referenciaAtual = Carbon::now()->format('Y-m');

        // So aplica quando o mes virou (ha fatura de um mes anterior).
        if ($ultimaReferencia === null || $ultimaReferencia >= $referenciaAtual) {
            return;
        }

        $destino = Plano::find($rede->plano_agendado_id);

        if ($destino !== null && $this->planoService->cabeNoPlano($rede, $destino)) {
            $rede->update(['plano_id' => $destino->id, 'plano_agendado_id' => null]);

            return;
        }

        Log::warning('Downgrade agendado cancelado: o plano destino nao cabe mais na rede.', [
            'rede_id' => $rede->id,
            'plano_agendado_id' => $rede->plano_agendado_id,
        ]);
        $rede->update(['plano_agendado_id' => null]);
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

                $status = StatusFatura::EmAberto;
                $pagoEm = null;

                if (! $eMesAtual) {
                    $status = StatusFatura::Paga;
                    $pagoEm = $vencimento->copy()->addDays(rand(0, 4));
                } elseif ($vencimento->isPast()) {
                    $status = StatusFatura::Paga;
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
