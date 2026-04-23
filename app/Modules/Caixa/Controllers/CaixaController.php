<?php

namespace App\Modules\Caixa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Caixa\DTOs\AbrirCaixaData;
use App\Modules\Caixa\DTOs\FecharCaixaData;
use App\Modules\Caixa\DTOs\MovimentoCaixaData;
use App\Modules\Caixa\DTOs\ReabrirCaixaData;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Caixa\Requests\AbrirCaixaRequest;
use App\Modules\Caixa\Requests\FecharCaixaRequest;
use App\Modules\Caixa\Requests\MovimentoCaixaRequest;
use App\Modules\Caixa\Requests\ReabrirCaixaRequest;
use App\Modules\Caixa\Services\CaixaService;
use App\Traits\TratamentoErros;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaixaController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private CaixaService $service,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $data = $request->query('data', today()->toDateString());
            $dataSelecionada = Carbon::parse($data);

            $caixa = $this->service->caixaDoDia($data);

            $totalEntradas = 0;
            $totalSaidas = 0;
            $totalReforcos = 0;
            $saldoAtual = 0;

            if ($caixa) {
                $caixa->load('movimentos', 'usuario', 'fechadoPor');
                $totalEntradas = $caixa->movimentos->where('tipo', 'entrada')->sum('valor');
                $totalSaidas = $caixa->movimentos->whereIn('tipo', ['saida', 'sangria'])->sum('valor');
                $totalReforcos = $caixa->movimentos->where('tipo', 'reforco')->sum('valor');
                $saldoAtual = $caixa->saldo_abertura + $totalEntradas + $totalReforcos - $totalSaidas;
            }

            return view('caixa::index', compact(
                'caixa', 'dataSelecionada', 'totalEntradas', 'totalSaidas', 'totalReforcos', 'saldoAtual'
            ));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar caixa');
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
