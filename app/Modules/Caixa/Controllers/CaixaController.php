<?php

namespace App\Modules\Caixa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Caixa\DTOs\AbrirCaixaData;
use App\Modules\Caixa\DTOs\FecharCaixaData;
use App\Modules\Caixa\DTOs\MovimentoCaixaData;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Caixa\Requests\AbrirCaixaRequest;
use App\Modules\Caixa\Requests\FecharCaixaRequest;
use App\Modules\Caixa\Requests\MovimentoCaixaRequest;
use App\Modules\Caixa\Services\CaixaService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CaixaController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private CaixaService $service,
    ) {}

    public function index(): View|RedirectResponse
    {
        try {
            $caixas = \App\Modules\Caixa\Models\Caixa::orderBy('data', 'desc')->get();

            return view('caixa::index', compact('caixas'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar caixas');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            return view('caixa::abrir');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de abertura de caixa');
        }
    }

    public function store(AbrirCaixaRequest $request): RedirectResponse
    {
        try {
            $dados = AbrirCaixaData::from($request->validated());
            $caixa = $this->service->abrir($dados->saldo_abertura, $dados->observacao);

            return redirect()->route('caixas.show', $caixa)->with('sucesso', 'Caixa aberto com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao abrir caixa');
        }
    }

    public function show(Caixa $caixa): View|RedirectResponse
    {
        try {
            $caixa->load('movimentos', 'usuario', 'fechadoPor');

            $totalEntradas = $caixa->movimentos->whereIn('tipo', ['entrada'])->sum('valor');
            $totalSaidas = $caixa->movimentos->whereIn('tipo', ['saida', 'sangria'])->sum('valor');
            $totalReforcos = $caixa->movimentos->where('tipo', 'reforco')->sum('valor');
            $saldoAtual = $caixa->saldo_abertura + $totalEntradas + $totalReforcos - $totalSaidas;

            return view('caixa::show', compact('caixa', 'totalEntradas', 'totalSaidas', 'totalReforcos', 'saldoAtual'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir caixa');
        }
    }

    public function fechar(FecharCaixaRequest $request, Caixa $caixa): RedirectResponse
    {
        try {
            $this->service->fechar($caixa, FecharCaixaData::from($request->validated()));

            return redirect()->route('caixas.show', $caixa)->with('sucesso', 'Caixa fechado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao fechar caixa');
        }
    }

    public function sangria(MovimentoCaixaRequest $request, Caixa $caixa): RedirectResponse
    {
        try {
            $this->service->registrarSangria($caixa, MovimentoCaixaData::from($request->validated()));

            return redirect()->route('caixas.show', $caixa)->with('sucesso', 'Sangria registrada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao registrar sangria');
        }
    }

    public function reforco(MovimentoCaixaRequest $request, Caixa $caixa): RedirectResponse
    {
        try {
            $this->service->registrarReforco($caixa, MovimentoCaixaData::from($request->validated()));

            return redirect()->route('caixas.show', $caixa)->with('sucesso', 'Reforço registrado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao registrar reforço');
        }
    }
}
