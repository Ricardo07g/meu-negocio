<?php

declare(strict_types=1);

namespace App\Modules\Pagamento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Caixa\Services\CaixaService;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\Pagamento\DTOs\RenegociarParcelaData;
use App\Modules\Pagamento\Models\{Pagamento, ParcelaPagamento};
use App\Modules\Pagamento\Requests\{CancelarParcelaRequest, RenegociarParcelaRequest, SalvarBaixaParcelaRequest};
use App\Modules\Pagamento\Services\PagamentoService;
use App\Traits\{DefineEmpresaDeCriacao, TratamentoErros};
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\View\View;

class PagamentoController extends Controller
{
    use DefineEmpresaDeCriacao;
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

            $formas = FormaPagamento::ativos()->orderBy('nome')->get();

            return view('pagamento::baixa', compact('parcela', 'formas'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de baixa');
        }
    }

    public function baixaParcela(SalvarBaixaParcelaRequest $request, ParcelaPagamento $parcela): RedirectResponse
    {
        try {
            return $this->comEmpresaDeCriacao((int) $parcela->empresa_id, function () use ($request, $parcela) {
                $this->caixaService->darBaixaParcelaPagamento(
                    $parcela,
                    (float) $request->valor,
                    FormaPagamento::findOrFail((int) $request->forma_pagamento),
                    $request->observacao,
                    (float) ($request->multa ?? 0),
                    (float) ($request->juros ?? 0),
                    (float) ($request->desconto ?? 0),
                );

                return redirect()->route('pagamentos.index')->with('sucesso', 'Recebimento registrado.');
            });
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao registrar recebimento');
        }
    }

    public function renegociarParcela(RenegociarParcelaRequest $request, ParcelaPagamento $parcela): RedirectResponse
    {
        try {
            return $this->comEmpresaDeCriacao((int) $parcela->empresa_id, function () use ($request, $parcela) {
                $this->service->renegociarParcela(
                    $parcela,
                    new RenegociarParcelaData(
                        data_vencimento: Carbon::parse($request->data_vencimento),
                        valor: (float) $request->valor,
                        observacao: $request->observacao,
                    )
                );

                return redirect()->route('pagamentos.index')->with('sucesso', 'Parcela renegociada.');
            });
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao renegociar parcela');
        }
    }

    public function cancelarParcela(CancelarParcelaRequest $request, ParcelaPagamento $parcela): RedirectResponse
    {
        try {
            return $this->comEmpresaDeCriacao((int) $parcela->empresa_id, function () use ($request, $parcela) {
                $this->service->cancelarParcela($parcela, $request->input('motivo'));

                return redirect()->route('pagamentos.index')->with('sucesso', 'Parcela cancelada.');
            });
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao cancelar parcela');
        }
    }

    public function contasAReceber(): RedirectResponse
    {
        return redirect()->route('pagamentos.index', ['status' => 'pendente']);
    }

    public function recibo(Pagamento $pagamento): Response|RedirectResponse
    {
        try {
            $this->authorize('view', $pagamento);
            $pagamento->load(['empresa', 'cliente', 'parcelas.baixas', 'agendamento.servico', 'vendaEtapas.servico', 'vendaProduto.itens']);
            $empresa = $pagamento->empresa;

            $pdf = Pdf::loadView('pagamento::recibo', compact('pagamento', 'empresa'));

            return $pdf->stream("comprovante-recebimento-{$pagamento->id}.pdf");
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao gerar comprovante');
        }
    }
}
