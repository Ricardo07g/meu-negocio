<?php

declare(strict_types=1);

namespace App\Modules\Venda\Controllers;

use App\Enums\{CondicaoPagamento, FormaPagamento, FormaRecebimentoPrazo};
use App\Http\Controllers\Controller;
use App\Modules\Agenda\DTOs\AgendamentoData;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Services\CaixaService;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Produto\Models\Produto;
use App\Modules\Servico\Models\Servico;
use App\Modules\Usuario\Models\Usuario;
use App\Modules\Venda\DTOs\VenderEtapasData;
use App\Modules\Venda\Models\{VendaEtapas, VendaProduto};
use App\Modules\Venda\Requests\{AtualizarVendaEtapasRequest, AtualizarVendaProdutoRequest, AtualizarVendaUnicoRequest, CriarVendaRequest};
use App\Modules\Venda\Services\VendaService;
use App\Support\ContextoEmpresa;
use App\Traits\TratamentoErros;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\View\View;

class VendaController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private VendaService $service,
        private CaixaService $caixaService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Agendamento::class);

            $filtros = $request->only([
                'q', 'periodo_preset', 'data_inicio', 'data_fim', 'tipo',
                'situacao_pagamento', 'forma_pagamento', 'status_venda',
                'atendente_id', 'valor_min', 'valor_max',
            ]);

            $vendas = $this->service->listar($filtros);

            $empresaId = ContextoEmpresa::resolver();

            $atendentes = ($empresaId ? Usuario::atendentesDaEmpresa($empresaId) : Usuario::where('atende', true))
                ->orderBy('nome')->get();

            return view('venda::index', compact('vendas', 'filtros', 'atendentes'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar vendas');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Agendamento::class);

            $empresaId = ContextoEmpresa::resolver();
            $atendentes = ($empresaId
                ? Usuario::atendentesDaEmpresa($empresaId)
                : Usuario::where('atende', true))
                ->orderBy('nome')->get();

            $clienteOld = old('cliente_id') ? Cliente::find(old('cliente_id')) : null;
            $servicoOld = old('servico_id') ? Servico::find(old('servico_id')) : null;

            $itensOld = [];
            foreach ((array) old('itens', []) as $item) {
                $produto = isset($item['produto_id']) ? Produto::find($item['produto_id']) : null;
                if ($produto) {
                    $itensOld[] = [
                        'produto_id' => $produto->id,
                        'nome' => $produto->nome,
                        'quantidade' => (int) ($item['quantidade'] ?? 1),
                        'valor_unitario' => (float) ($item['valor_unitario'] ?? $produto->valor_venda),
                        'desconto' => (float) ($item['desconto'] ?? 0),
                        'acrescimo' => (float) ($item['acrescimo'] ?? 0),
                    ];
                }
            }

            return view('venda::create', compact('atendentes', 'clienteOld', 'servicoOld', 'itensOld'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de venda');
        }
    }

    public function store(CriarVendaRequest $request): RedirectResponse
    {
        try {
            // ME-010: quando ha multiplas empresas selecionadas no header, o
            // formulario envia empresa_id; setamos como override de criacao
            // para que toda a cascata (Venda, Pagamento, Parcela, Baixa,
            // MovimentoCaixa) use a mesma empresa via EmpresaTrait::creating.
            if ($request->filled('empresa_id')) {
                session(['empresa_criacao_atual' => (int) $request->empresa_id]);
            }

            $condicao = CondicaoPagamento::from($request->condicao_pagamento);
            $aVista = $condicao === CondicaoPagamento::AVista;

            $forma = $request->forma_pagamento
                ? FormaPagamento::from($request->forma_pagamento)
                : null;

            $formaRecebimentoPrazo = $request->forma_recebimento_prazo
                ? FormaRecebimentoPrazo::from($request->forma_recebimento_prazo)
                : null;

            $numeroParcelas = $condicao->geraParcelas() ? (int) $request->numero_parcelas : null;
            $primeiroVencimento = $condicao->geraParcelas()
                ? Carbon::parse($request->primeiro_vencimento)
                : now();
            $mesReferencia = Carbon::parse($request->mes_referencia)->startOfMonth();

            $parcelasPersonalizadas = $this->extrairParcelasPersonalizadas($request);

            if ($aVista && ! $this->caixaService->caixaAberto()) {
                return redirect()->back()->withInput()
                    ->with('erro', 'É necessário abrir o caixa para registrar vendas à vista.');
            }

            if ($request->tipo_venda === 'produto') {
                $itens = $request->input('itens', []);

                $this->service->criarVendaProduto(
                    $request->cliente_id,
                    $itens,
                    $condicao,
                    $mesReferencia,
                    $forma,
                    $numeroParcelas,
                    $primeiroVencimento,
                    $request->data,
                    $request->observacao,
                    $parcelasPersonalizadas,
                    $formaRecebimentoPrazo,
                );

                return redirect()->route('vendas.index')->with('sucesso', 'Venda de produto registrada com sucesso.');
            }

            $servico = Servico::findOrFail($request->servico_id);

            if ($servico->isEtapas()) {
                $this->service->criarEtapas(
                    VenderEtapasData::from($request->validated()),
                    $condicao,
                    $mesReferencia,
                    $forma,
                    $numeroParcelas,
                    $primeiroVencimento,
                    $parcelasPersonalizadas,
                    $formaRecebimentoPrazo,
                );
                $msg = 'Serviço em etapas vendido com sucesso! Agendamentos criados.';
            } else {
                $payload = $request->validated();
                $payload['inicio'] = Carbon::createFromFormat('Y-m-d H:i', $payload['data'].' '.$payload['horario']);
                $this->service->criarUnico(
                    AgendamentoData::from($payload),
                    $condicao,
                    $mesReferencia,
                    $forma,
                    $numeroParcelas,
                    $primeiroVencimento,
                    $parcelasPersonalizadas,
                    $formaRecebimentoPrazo,
                );
                $msg = 'Agendamento criado com sucesso.';
            }

            return redirect()->route('vendas.index')->with('sucesso', $msg);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao registrar venda');
        } finally {
            session()->forget('empresa_criacao_atual');
        }
    }

    /**
     * Converte o array cru de parcelas editadas no preview em estrutura
     * consumível pelo CriarPagamentoComParcelasAction (datas como Carbon).
     * Retorna null se não há parcelas no request.
     */
    private function extrairParcelasPersonalizadas(CriarVendaRequest $request): ?array
    {
        $raw = $request->input('parcelas');
        if (empty($raw) || ! is_array($raw)) {
            return null;
        }

        return array_map(function (array $p) {
            return [
                'numero' => (int) $p['numero'],
                'total' => (int) $p['total'],
                'valor' => (float) $p['valor'],
                'data_vencimento' => Carbon::parse($p['data_vencimento']),
                'mes_referencia' => Carbon::parse($p['mes_referencia']),
            ];
        }, array_values($raw));
    }

    public function cancelarUnico(Agendamento $agendamento): RedirectResponse
    {
        try {
            $this->authorize('cancel', $agendamento);
            $this->service->cancelarUnico($agendamento);

            return redirect()->route('vendas.index')->with('sucesso', 'Agendamento cancelado.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao cancelar venda de serviço único');
        }
    }

    public function cancelarEtapas(VendaEtapas $etapas): RedirectResponse
    {
        try {
            $this->authorize('cancel', $etapas);
            $this->service->cancelarEtapas($etapas);

            return redirect()->route('vendas.index')->with('sucesso', 'Venda em etapas cancelada. Agendamentos pendentes foram cancelados.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao cancelar venda em etapas');
        }
    }

    public function cancelarProduto(VendaProduto $vendaProduto): RedirectResponse
    {
        try {
            $this->service->cancelarVendaProduto($vendaProduto);

            return redirect()->route('vendas.index')->with('sucesso', 'Venda cancelada. Estoque devolvido e pagamento estornado.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao cancelar venda de produto');
        }
    }

    public function editUnico(Agendamento $agendamento): View|RedirectResponse
    {
        try {
            $agendamento->load(['cliente', 'servico', 'pagamento.parcelas']);
            if (! $this->service->podeEditar($agendamento->pagamento) || ! in_array($agendamento->status->value, ['agendado', 'confirmado'])) {
                return redirect()->route('vendas.index')->with('erro', 'Venda não pode ser editada.');
            }

            return view('venda::edit-unico', compact('agendamento'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao abrir edição de venda de serviço único');
        }
    }

    public function updateUnico(AtualizarVendaUnicoRequest $request, Agendamento $agendamento): RedirectResponse
    {
        try {
            $this->service->atualizarUnico($agendamento, $request->validated());

            return redirect()->route('vendas.index')->with('sucesso', 'Agendamento atualizado.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar venda de serviço único');
        }
    }

    public function editEtapas(VendaEtapas $etapas): View|RedirectResponse
    {
        try {
            $this->authorize('view', $etapas);
            $etapas->load(['cliente', 'servico', 'pagamento.parcelas']);
            if (! $this->service->podeEditar($etapas->pagamento) || $etapas->status->value !== 'ativo') {
                return redirect()->route('vendas.index')->with('erro', 'Venda em etapas não pode ser editada.');
            }

            return view('venda::edit-etapas', compact('etapas'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao abrir edição de venda em etapas');
        }
    }

    public function updateEtapas(AtualizarVendaEtapasRequest $request, VendaEtapas $etapas): RedirectResponse
    {
        try {
            $this->authorize('view', $etapas);
            $this->service->atualizarEtapas($etapas, $request->validated());

            return redirect()->route('vendas.index')->with('sucesso', 'Venda em etapas atualizada.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar venda em etapas');
        }
    }

    public function editProduto(VendaProduto $vendaProduto): View|RedirectResponse
    {
        try {
            $vendaProduto->load(['cliente', 'itens.produto', 'pagamento.parcelas']);
            if (! $this->service->podeEditar($vendaProduto->pagamento) || $vendaProduto->status->value !== 'ativa') {
                return redirect()->route('vendas.index')->with('erro', 'Venda não pode ser editada.');
            }

            return view('venda::edit-produto', compact('vendaProduto'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao abrir edição de venda de produto');
        }
    }

    public function updateProduto(AtualizarVendaProdutoRequest $request, VendaProduto $vendaProduto): RedirectResponse
    {
        try {
            $this->service->atualizarVendaProduto($vendaProduto, $request->validated());

            return redirect()->route('vendas.index')->with('sucesso', 'Venda atualizada.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar venda de produto');
        }
    }

    public function recibo(string $tipo, int $id): Response|RedirectResponse
    {
        try {
            $empresa = auth()->user()->empresa ?? null;

            $dados = match ($tipo) {
                'unico' => $this->dadosReciboUnico($id),
                'etapas' => $this->dadosReciboEtapas($id),
                'produto' => $this->dadosReciboProduto($id),
                default => abort(404, 'Tipo de venda inválido'),
            };

            $pdf = Pdf::loadView('venda::recibo', array_merge($dados, [
                'empresa' => $empresa,
                'tipo' => $tipo,
            ]));

            return $pdf->stream("recibo-{$tipo}-{$id}.pdf");
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao gerar recibo');
        }
    }

    private function dadosReciboUnico(int $id): array
    {
        $agendamento = Agendamento::with(['cliente', 'servico', 'atendente', 'pagamento.parcelas.baixas'])->findOrFail($id);
        $pagamento = $agendamento->pagamento;
        $valor = $agendamento->servico->valor ?? 0;

        return [
            'numero' => $agendamento->id,
            'tipoLabel' => 'Serviço Único',
            'statusLabel' => $agendamento->status->label(),
            'dataVenda' => $agendamento->created_at,
            'cliente' => $agendamento->cliente,
            'atendente' => $agendamento->atendente,
            'atendenteLabel' => 'Atendente',
            'servico' => $agendamento->servico,
            'inicio' => $agendamento->inicio,
            'fim' => $agendamento->fim,
            'itens' => collect(),
            'etapas' => null,
            'qtdEtapas' => null,
            'etapasRealizadas' => null,
            'subtotal' => null,
            'desconto' => 0,
            'acrescimo' => 0,
            'valorTotal' => $valor,
            'pagamento' => $pagamento,
        ];
    }

    private function dadosReciboEtapas(int $id): array
    {
        $etapas = VendaEtapas::with(['cliente', 'servico', 'atendente', 'agendamentos', 'pagamento.parcelas.baixas'])->findOrFail($id);
        $this->authorize('view', $etapas);
        $subtotal = $etapas->valor_total + $etapas->desconto - $etapas->acrescimo;

        return [
            'numero' => $etapas->id,
            'tipoLabel' => 'Serviço em Etapas',
            'statusLabel' => $etapas->status->label(),
            'dataVenda' => $etapas->created_at,
            'cliente' => $etapas->cliente,
            'atendente' => $etapas->atendente,
            'atendenteLabel' => 'Atendente',
            'servico' => $etapas->servico,
            'inicio' => null,
            'fim' => null,
            'itens' => collect(),
            'etapas' => $etapas->agendamentos->sortBy('inicio')->values(),
            'qtdEtapas' => $etapas->qtd_etapas,
            'etapasRealizadas' => $etapas->etapasRealizadas(),
            'subtotal' => $etapas->desconto > 0 || $etapas->acrescimo > 0 ? $subtotal : null,
            'desconto' => (float) $etapas->desconto,
            'acrescimo' => (float) $etapas->acrescimo,
            'valorTotal' => (float) $etapas->valor_total,
            'pagamento' => $etapas->pagamento,
        ];
    }

    private function dadosReciboProduto(int $id): array
    {
        $venda = VendaProduto::with(['cliente', 'usuario', 'itens.produto', 'pagamento.parcelas.baixas'])->findOrFail($id);

        return [
            'numero' => $venda->id,
            'tipoLabel' => 'Venda de produtos',
            'statusLabel' => $venda->status->label(),
            'dataVenda' => $venda->data ?? $venda->created_at,
            'cliente' => $venda->cliente,
            'atendente' => $venda->usuario,
            'atendenteLabel' => 'Vendedor',
            'servico' => null,
            'inicio' => null,
            'fim' => null,
            'itens' => $venda->itens,
            'etapas' => null,
            'qtdEtapas' => null,
            'etapasRealizadas' => null,
            'subtotal' => (float) $venda->subtotal,
            'desconto' => (float) $venda->desconto,
            'acrescimo' => (float) $venda->acrescimo,
            'valorTotal' => (float) $venda->valor_total,
            'pagamento' => $venda->pagamento,
        ];
    }
}
