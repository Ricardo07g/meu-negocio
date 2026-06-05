<?php

declare(strict_types=1);

namespace App\Modules\Despesa\Controllers;

use App\Enums\{CondicaoPagamento, FormaPagamento, FormaRecebimentoPrazo};
use App\Http\Controllers\Controller;
use App\Modules\Caixa\Services\CaixaService;
use App\Modules\Despesa\DTOs\CriarDespesaData;
use App\Modules\Despesa\Models\{CategoriaDespesa, Despesa, ParcelaDespesa};
use App\Modules\Despesa\Requests\SalvarDespesaRequest;
use App\Modules\Despesa\Services\DespesaService;
use App\Modules\Pagamento\Requests\{CancelarParcelaRequest, SalvarBaixaParcelaRequest};
use App\Traits\TratamentoErros;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request, Response};
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
            $filtros = $request->only(['q', 'status', 'categoria_id', 'situacao', 'mes_referencia']);
            $despesas = $this->service->listar($filtros);
            $categorias = CategoriaDespesa::orderBy('descricao')->get();

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
            // ME-010: setar empresa de criacao quando ha multiplas selecionadas
            // para garantir que Despesa + ParcelaDespesa + BaixaDespesa usem a
            // mesma empresa via EmpresaTrait::creating override.
            if ($request->filled('empresa_id')) {
                session(['empresa_criacao_atual' => (int) $request->empresa_id]);
            }

            $condicao = CondicaoPagamento::from($request->condicao_pagamento);

            $forma = $request->forma_pagamento
                ? FormaPagamento::from($request->forma_pagamento)
                : null;

            $formaRecebimentoPrazo = $request->forma_recebimento_prazo
                ? FormaRecebimentoPrazo::from($request->forma_recebimento_prazo)
                : null;

            $numeroParcelas = $condicao->geraParcelas() ? (int) $request->numero_parcelas : null;

            $parcelasPersonalizadas = null;
            $raw = $request->input('parcelas');
            if (! empty($raw) && is_array($raw)) {
                $parcelasPersonalizadas = array_map(function (array $p) {
                    return [
                        'numero' => (int) $p['numero'],
                        'total' => (int) $p['total'],
                        'valor' => (float) $p['valor'],
                        'data_vencimento' => Carbon::parse($p['data_vencimento']),
                        'mes_referencia' => Carbon::parse($p['mes_referencia']),
                    ];
                }, array_values($raw));
            }

            $data = new CriarDespesaData(
                nome: $request->nome,
                valor_total: (float) $request->valor_total,
                condicao_pagamento: $condicao,
                mes_referencia: Carbon::parse($request->mes_referencia)->startOfMonth(),
                data_emissao: Carbon::parse($request->data_emissao),
                primeiro_vencimento: Carbon::parse($request->primeiro_vencimento),
                categoria_despesa_id: $request->categoria_despesa_id,
                fornecedor_nome: $request->fornecedor_nome,
                documento: $request->documento,
                observacoes: $request->observacoes,
                numero_parcelas: $numeroParcelas,
                forma_pagamento_avista: $forma,
                forma_recebimento_prazo: $formaRecebimentoPrazo,
                parcelas_personalizadas: $parcelasPersonalizadas,
            );

            $this->service->criar($data);

            return redirect()->route('despesas.index')->with('sucesso', 'Despesa criada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar despesa');
        } finally {
            session()->forget('empresa_criacao_atual');
        }
    }

    public function cancelar(Despesa $despesa): RedirectResponse
    {
        try {
            $this->authorize('update', $despesa);
            session(['empresa_criacao_atual' => (int) $despesa->empresa_id]);

            $this->service->cancelarDespesa($despesa);

            return redirect()->route('despesas.index')->with('sucesso', 'Despesa cancelada.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao cancelar despesa');
        } finally {
            session()->forget('empresa_criacao_atual');
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

    public function baixaParcelaForm(ParcelaDespesa $parcela): View|RedirectResponse
    {
        try {
            $parcela->load(['despesa.categoria']);
            if ($parcela->saldoRestante() <= 0) {
                return redirect()->route('despesas.index')->with('erro', 'Esta parcela já está quitada.');
            }

            return view('despesa::baixa', compact('parcela'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de baixa');
        }
    }

    public function baixaParcela(SalvarBaixaParcelaRequest $request, ParcelaDespesa $parcela): RedirectResponse
    {
        try {
            session(['empresa_criacao_atual' => (int) $parcela->empresa_id]);

            $this->caixaService->darBaixaParcelaDespesa(
                $parcela,
                (float) $request->valor,
                FormaPagamento::from($request->forma_pagamento),
                $request->observacao,
                (float) ($request->multa ?? 0),
                (float) ($request->juros ?? 0),
                (float) ($request->desconto ?? 0),
            );

            return redirect()->route('despesas.index')->with('sucesso', 'Pagamento registrado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao registrar pagamento');
        } finally {
            session()->forget('empresa_criacao_atual');
        }
    }

    public function cancelarParcela(CancelarParcelaRequest $request, ParcelaDespesa $parcela): RedirectResponse
    {
        try {
            session(['empresa_criacao_atual' => (int) $parcela->empresa_id]);

            $this->service->cancelarParcela($parcela, $request->input('motivo'));

            return redirect()->route('despesas.index')->with('sucesso', 'Parcela cancelada.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao cancelar parcela');
        } finally {
            session()->forget('empresa_criacao_atual');
        }
    }

    public function contasAPagar(): RedirectResponse
    {
        return redirect()->route('despesas.index', ['status' => 'pendente']);
    }

    public function recibo(Despesa $despesa): Response|RedirectResponse
    {
        try {
            $this->authorize('view', $despesa);
            $despesa->load(['categoria', 'parcelas.baixas']);
            $empresa = auth()->user()->empresa ?? null;

            $pdf = Pdf::loadView('despesa::recibo', compact('despesa', 'empresa'));

            return $pdf->stream("comprovante-pagamento-{$despesa->id}.pdf");
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao gerar comprovante');
        }
    }
}
