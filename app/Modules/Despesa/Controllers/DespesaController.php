<?php

namespace App\Modules\Despesa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Caixa\Services\CaixaService;
use App\Modules\Despesa\DTOs\DespesaData;
use App\Modules\Despesa\Models\CategoriaDespesa;
use App\Modules\Despesa\Models\Despesa;
use App\Modules\Despesa\Requests\SalvarDespesaRequest;
use App\Modules\Despesa\Services\DespesaService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DespesaController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private DespesaService $service,
        private CaixaService $caixaService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Despesa::class);
            $filtros = $request->only(['q', 'status', 'categoria_id', 'situacao']);
            $despesas = $this->service->listar($filtros);
            $categorias = \App\Modules\Despesa\Models\CategoriaDespesa::orderBy('descricao')->get();

            return view('despesa::index', compact('despesas', 'filtros', 'categorias'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar despesas');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Despesa::class);
            $categorias = CategoriaDespesa::ativos()->orderBy('descricao')->get();

            return view('despesa::create', compact('categorias'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de despesa');
        }
    }

    public function store(SalvarDespesaRequest $request): RedirectResponse
    {
        try {
            $this->service->criar(DespesaData::from($request->validated()));

            return redirect()->route('despesas.index')->with('sucesso', 'Despesa criada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar despesa');
        }
    }

    public function edit(Despesa $despesa): View|RedirectResponse
    {
        try {
            $this->authorize('update', $despesa);
            $categorias = CategoriaDespesa::ativos()->orderBy('descricao')->get();

            return view('despesa::edit', compact('despesa', 'categorias'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de despesa');
        }
    }

    public function update(SalvarDespesaRequest $request, Despesa $despesa): RedirectResponse
    {
        try {
            $this->authorize('update', $despesa);
            $this->service->atualizar($despesa, DespesaData::from($request->validated()));

            return redirect()->route('despesas.index')->with('sucesso', 'Despesa atualizada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar despesa');
        }
    }

    public function destroy(Despesa $despesa): RedirectResponse
    {
        try {
            $this->authorize('delete', $despesa);
            $this->service->excluir($despesa);

            return redirect()->route('despesas.index')->with('sucesso', 'Despesa excluída com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao excluir despesa');
        }
    }

    public function baixaForm(Despesa $despesa): View|RedirectResponse
    {
        try {
            $this->authorize('view', $despesa);

            if ($despesa->status->value !== 'pendente' || $despesa->saldoRestante() <= 0) {
                return redirect()->route('despesas.index')->with('erro', 'Esta despesa não possui saldo a pagar.');
            }

            $despesa->load(['categoria', 'baixas']);

            return view('despesa::baixa', compact('despesa'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de baixa');
        }
    }

    public function baixa(Request $request, Despesa $despesa): RedirectResponse
    {
        try {
            if (!$this->caixaService->caixaAberto()) {
                return redirect()->back()->with('erro', 'É necessário abrir o caixa para registrar a baixa.');
            }

            $request->validate([
                'valor' => ['required', 'numeric', 'min:0.01'],
                'forma_pagamento' => ['required', 'string'],
                'observacao' => ['nullable', 'string'],
            ]);

            $this->caixaService->darBaixaDespesa(
                $despesa,
                (float) $request->valor,
                $request->forma_pagamento,
                $request->observacao,
            );

            return redirect()->route('despesas.index')->with('sucesso', 'Baixa registrada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao registrar baixa');
        }
    }

    public function contasAPagar(): RedirectResponse
    {
        return redirect()->route('despesas.index', ['status' => 'pendente']);
    }

    public function recibo(Despesa $despesa): \Illuminate\Http\Response|RedirectResponse
    {
        try {
            $this->authorize('view', $despesa);
            $despesa->load(['categoria', 'baixas']);
            $empresa = auth()->user()->empresa ?? null;

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('despesa::recibo', compact('despesa', 'empresa'));

            return $pdf->stream("comprovante-pagamento-{$despesa->id}.pdf");
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao gerar comprovante');
        }
    }
}
