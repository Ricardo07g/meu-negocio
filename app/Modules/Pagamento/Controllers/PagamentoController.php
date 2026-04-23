<?php

namespace App\Modules\Pagamento\Controllers;

use App\Enums\FormaPagamento;
use App\Http\Controllers\Controller;
use App\Modules\Caixa\Services\CaixaService;
use App\Modules\Pagamento\DTOs\RenegociarParcelaData;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Pagamento\Models\ParcelaPagamento;
use App\Modules\Pagamento\Services\PagamentoService;
use App\Traits\TratamentoErros;
use Carbon\Carbon;
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
            $filtros = $request->only(['q', 'status', 'origem', 'situacao', 'mes_referencia']);
            $pagamentos = $this->service->listar($filtros);

            return view('pagamento::index', compact('pagamentos', 'filtros'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar pagamentos');
        }
    }

    public function baixaParcelaForm(ParcelaPagamento $parcela): View|RedirectResponse
    {
        try {
            $parcela->load(['pagamento.cliente', 'baixas']);
            if ($parcela->saldoRestante() <= 0) {
                return redirect()->route('pagamentos.index')->with('erro', 'Esta parcela já está quitada.');
            }

            return view('pagamento::baixa', compact('parcela'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de baixa');
        }
    }

    public function baixaParcela(Request $request, ParcelaPagamento $parcela): RedirectResponse
    {
        try {
            $request->validate([
                'valor' => ['required', 'numeric', 'min:0.01'],
                'multa' => ['nullable', 'numeric', 'min:0'],
                'juros' => ['nullable', 'numeric', 'min:0'],
                'desconto' => ['nullable', 'numeric', 'min:0'],
                'forma_pagamento' => ['required', 'string'],
                'observacao' => ['nullable', 'string'],
            ]);

            $this->caixaService->darBaixaParcelaPagamento(
                $parcela,
                (float) $request->valor,
                FormaPagamento::from($request->forma_pagamento),
                $request->observacao,
                (float) ($request->multa ?? 0),
                (float) ($request->juros ?? 0),
                (float) ($request->desconto ?? 0),
            );

            return redirect()->route('pagamentos.index')->with('sucesso', 'Recebimento registrado.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao registrar recebimento');
        }
    }

    public function renegociarParcela(Request $request, ParcelaPagamento $parcela): RedirectResponse
    {
        try {
            $request->validate([
                'data_vencimento' => ['required', 'date'],
                'valor' => ['required', 'numeric', 'min:0.01'],
                'observacao' => ['nullable', 'string'],
            ]);

            $this->service->renegociarParcela(
                $parcela,
                new RenegociarParcelaData(
                    data_vencimento: Carbon::parse($request->data_vencimento),
                    valor: (float) $request->valor,
                    observacao: $request->observacao,
                )
            );

            return redirect()->route('pagamentos.index')->with('sucesso', 'Parcela renegociada.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao renegociar parcela');
        }
    }

    public function cancelarParcela(Request $request, ParcelaPagamento $parcela): RedirectResponse
    {
        try {
            $this->service->cancelarParcela($parcela, $request->input('motivo'));

            return redirect()->route('pagamentos.index')->with('sucesso', 'Parcela cancelada.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao cancelar parcela');
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
            $pagamento->load(['cliente', 'parcelas.baixas', 'agendamento.servico', 'vendaPacote.servico', 'vendaProduto.itens']);
            $empresa = auth()->user()->empresa ?? null;

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pagamento::recibo', compact('pagamento', 'empresa'));

            return $pdf->stream("comprovante-recebimento-{$pagamento->id}.pdf");
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao gerar comprovante');
        }
    }
}
