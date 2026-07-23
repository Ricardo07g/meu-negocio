<?php

declare(strict_types=1);

namespace App\Modules\Caixa\Controllers;

use App\Enums\TipoLancamento;
use App\Http\Controllers\Controller;
use App\Modules\Caixa\DTOs\{AbrirCaixaData, FecharCaixaData, MovimentoCaixaData, ReabrirCaixaData};
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Caixa\Requests\{AbrirCaixaRequest, FecharCaixaRequest, MovimentoCaixaRequest, ReabrirCaixaRequest};
use App\Modules\Caixa\Services\{CaixaService, ResumoDiaService};
use App\Traits\TratamentoErros;
use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

class CaixaController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private CaixaService $service,
        private ResumoDiaService $resumoDia,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $data = $request->query('data', today()->toDateString());
            $dataSelecionada = Carbon::parse($data);

            $totalEntradas = 0;
            $totalSaidas = 0;
            $totalReforcos = 0;
            $saldoAtual = 0;

            // Caixa Diario opera em 1 empresa. Se o usuario tem multiplas
            // empresas acessiveis e ainda nao escolheu uma via filtro, escolhe
            // a primeira silenciosamente para evitar bloqueio na entrada da tela.
            $contexto = session('empresa_contexto_atual');
            $empresasAtuais = (array) session('empresas_atuais', []);
            if ($contexto === null && count($empresasAtuais) > 1) {
                session(['empresa_contexto_atual' => (int) reset($empresasAtuais)]);
            }
            $caixa = $this->service->caixaDoDia($data);

            if ($caixa) {
                $caixa->load('lancamentos', 'usuario', 'fechadoPor');
                $creditos = $caixa->lancamentos->where('tipo', TipoLancamento::Credito);
                $debitos = $caixa->lancamentos->where('tipo', TipoLancamento::Debito);
                $totalReforcos = $creditos->where('categoria', 'reforco')->sum('valor');
                $totalEntradas = $creditos->where('categoria', '!=', 'reforco')->sum('valor');
                // Saídas = despesas + sangrias + estornos (todo débito reduz a gaveta).
                $totalSaidas = $debitos->sum('valor');
                $saldoAtual = $caixa->saldo_abertura + $totalEntradas + $totalReforcos - $totalSaidas;
            }

            // Panorama do dia por forma (recebido/estornado/liquido). Independe de
            // haver caixa aberto — cartao/pix nao passam pela gaveta. Eixo disjunto
            // do saldo acima (que vem so dos lancamentos da gaveta).
            $resumo = $this->resumoDia->porForma($data);

            return view('caixa::index', compact(
                'caixa', 'dataSelecionada', 'totalEntradas', 'totalSaidas', 'totalReforcos', 'saldoAtual', 'resumo'
            ));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar caixa');
        }
    }

    /**
     * Relatório de recebimentos por período (por forma + detalhe), no regime
     * "quando o cliente pagou" (a baixa) — a casa única para ver TODOS os
     * recebimentos, inclusive os que não passam pela gaveta (cartão/pix).
     * Eixo disjunto do saldo do caixa (ADR-0011): não há saldo de banco.
     */
    public function recebimentos(Request $request): View|RedirectResponse
    {
        try {
            $de = Carbon::parse($request->query('de', today()->startOfMonth()->toDateString()))->startOfDay();
            $ate = Carbon::parse($request->query('ate', today()->toDateString()))->endOfDay();
            if ($de->gt($ate)) {
                [$de, $ate] = [$ate->copy()->startOfDay(), $de->copy()->endOfDay()];
            }

            // Opera em 1 empresa (mesma resolução do index do Caixa Diário).
            $contexto = session('empresa_contexto_atual');
            $empresasAtuais = (array) session('empresas_atuais', []);
            if ($contexto === null && count($empresasAtuais) > 1) {
                session(['empresa_contexto_atual' => (int) reset($empresasAtuais)]);
            }

            $deStr = $de->toDateString();
            $ateStr = $ate->toDateString();

            $resumo = $this->resumoDia->porPeriodo($deStr, $ateStr);
            $recebimentos = $this->resumoDia->recebimentos($deStr, $ateStr);

            return view('caixa::recebimentos', [
                'resumo' => $resumo,
                'recebimentos' => $recebimentos,
                'de' => $deStr,
                'ate' => $ateStr,
            ]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar recebimentos');
        }
    }

    public function store(AbrirCaixaRequest $request): RedirectResponse
    {
        try {
            $dados = AbrirCaixaData::from($request->validated());
            $this->service->abrir($dados->saldo_abertura, $dados->data, $dados->observacao);

            return redirect()->route('caixas.index', ['data' => $dados->data])->with('sucesso', 'Caixa aberto com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao abrir caixa');
        }
    }

    public function show(Caixa $caixa): RedirectResponse
    {
        return redirect()->route('caixas.index', ['data' => $caixa->data instanceof Carbon ? $caixa->data->toDateString() : $caixa->data]);
    }

    public function fechar(FecharCaixaRequest $request, Caixa $caixa): RedirectResponse
    {
        try {
            $dados = FecharCaixaData::from($request->validated());
            $this->service->fechar($caixa, $dados->saldo_fechamento, $dados->observacao);
            $data = $caixa->data instanceof Carbon ? $caixa->data->toDateString() : $caixa->data;

            return redirect()->route('caixas.index', ['data' => $data])->with('sucesso', 'Caixa fechado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao fechar caixa');
        }
    }

    public function reabrir(ReabrirCaixaRequest $request, Caixa $caixa): RedirectResponse
    {
        try {
            $this->authorize('update', $caixa);
            $dados = ReabrirCaixaData::from($request->validated());
            $this->service->reabrir($caixa, $dados->motivo);
            $data = $caixa->data instanceof Carbon ? $caixa->data->toDateString() : $caixa->data;

            return redirect()->route('caixas.index', ['data' => $data])->with('sucesso', 'Caixa reaberto com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao reabrir caixa');
        }
    }

    public function sangria(MovimentoCaixaRequest $request, Caixa $caixa): RedirectResponse
    {
        try {
            $dados = MovimentoCaixaData::from($request->validated());
            $this->service->registrarSangria($caixa, $dados->valor, $dados->descricao);
            $data = $caixa->data instanceof Carbon ? $caixa->data->toDateString() : $caixa->data;

            return redirect()->route('caixas.index', ['data' => $data])->with('sucesso', 'Sangria registrada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao registrar sangria');
        }
    }

    public function reforco(MovimentoCaixaRequest $request, Caixa $caixa): RedirectResponse
    {
        try {
            $dados = MovimentoCaixaData::from($request->validated());
            $this->service->registrarReforco($caixa, $dados->valor, $dados->descricao);
            $data = $caixa->data instanceof Carbon ? $caixa->data->toDateString() : $caixa->data;

            return redirect()->route('caixas.index', ['data' => $data])->with('sucesso', 'Reforço registrado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao registrar reforço');
        }
    }
}
