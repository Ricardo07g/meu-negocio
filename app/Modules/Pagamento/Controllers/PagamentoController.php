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
            $filtros = $request->only(['q', 'status', 'origem', 'situacao']);
            $pagamentos = $this->service->listar($filtros);

            return view('pagamento::index', compact('pagamentos', 'filtros'));
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

    public function baixaForm(Pagamento $pagamento): View|RedirectResponse
    {
        try {
            $this->authorize('view', $pagamento);

            if ($pagamento->status->value !== 'pendente' || $pagamento->saldoRestante() <= 0) {
                return redirect()->route('pagamentos.index')->with('erro', 'Este pagamento não possui saldo a receber.');
            }

            $pagamento->load(['cliente', 'agendamento.servico', 'vendaPacote.servico', 'vendaProduto.itens', 'baixas']);

            return view('pagamento::baixa', compact('pagamento'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de baixa');
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
                'multa' => ['nullable', 'numeric', 'min:0'],
                'juros' => ['nullable', 'numeric', 'min:0'],
                'forma_pagamento' => ['required', 'string'],
                'observacao' => ['nullable', 'string'],
            ]);

            $this->caixaService->darBaixaPagamento(
                $pagamento,
                (float) $request->valor,
                $request->forma_pagamento,
                $request->observacao,
                (float) ($request->multa ?? 0),
                (float) ($request->juros ?? 0),
            );

            return redirect()->route('pagamentos.index')->with('sucesso', 'Pagamento registrado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao registrar baixa');
        }
    }

    public function contasAReceber(): RedirectResponse
    {
        return redirect()->route('pagamentos.index', ['status' => 'pendente']);
    }

    public function recibo(Pagamento $pagamento): \Illuminate\Http\Response|RedirectResponse
    {
        try {
            $this->authorize('view', $pagamento);
            $pagamento->load(['cliente', 'baixas', 'agendamento.servico', 'vendaPacote.servico', 'vendaProduto.itens']);
            $empresa = auth()->user()->empresa ?? null;

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pagamento::recibo', compact('pagamento', 'empresa'));

            return $pdf->stream("comprovante-recebimento-{$pagamento->id}.pdf");
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao gerar comprovante');
        }
    }
}
