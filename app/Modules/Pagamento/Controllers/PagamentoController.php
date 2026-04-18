<?php

namespace App\Modules\Pagamento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pagamento\DTOs\RegistrarPagamentoData;
use App\Modules\Pagamento\Requests\RegistrarPagamentoRequest;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Pagamento\Services\PagamentoService;
use App\Modules\Caixa\Services\CaixaService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PagamentoController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private PagamentoService $service,
        private CaixaService $caixaService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Pagamento::class);
            $filtro = $request->query('status', 'todos');
            $pagamentos = $this->service->listar($filtro);

            return view('pagamento::index', compact('pagamentos', 'filtro'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar pagamentos');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Pagamento::class);

            return view('pagamento::create');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de pagamento');
        }
    }

    public function store(RegistrarPagamentoRequest $request): RedirectResponse
    {
        try {
            $this->service->registrar(RegistrarPagamentoData::from($request->validated()));

            return redirect()->route('pagamentos.index')->with('sucesso', 'Pagamento registrado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar pagamento');
        }
    }

    public function show(Pagamento $pagamento): View|RedirectResponse
    {
        try {
            $this->authorize('view', $pagamento);
            $pagamento->load(['cliente', 'agendamento.servico', 'vendaPacote.servico', 'vendaProduto.itens', 'baixas']);

            return view('pagamento::show', compact('pagamento'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir pagamento');
        }
    }

    public function baixa(Request $request, Pagamento $pagamento): RedirectResponse
    {
        try {
            if (!$this->caixaService->caixaAberto()) {
                return redirect()->back()->with('erro', 'É necessário abrir o caixa para registrar pagamentos.');
            }

            $request->validate([
                'valor' => ['required', 'numeric', 'min:0.01'],
                'forma_pagamento' => ['required', 'string'],
                'observacao' => ['nullable', 'string'],
            ]);

            $this->caixaService->darBaixaPagamento(
                $pagamento,
                (float) $request->valor,
                $request->forma_pagamento,
                $request->observacao,
            );

            return redirect()->route('pagamentos.show', $pagamento)->with('sucesso', 'Pagamento registrado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao registrar baixa');
        }
    }

    public function contasAReceber(): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Pagamento::class);
            $pagamentos = $this->service->listarContasAReceber();
            $totalReceber = $pagamentos->sum(fn ($p) => $p->valor - $p->valor_pago);

            return view('pagamento::contas-a-receber', compact('pagamentos', 'totalReceber'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar contas a receber');
        }
    }
}
