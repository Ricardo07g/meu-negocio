<?php

declare(strict_types=1);

namespace App\Modules\Venda\Controllers;

use App\Enums\{CondicaoPagamento, FormaRecebimentoPrazo};
use App\Http\Controllers\Controller;
use App\Modules\Agenda\DTOs\AgendamentoData;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Services\CaixaService;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\Produto\Models\Produto;
use App\Modules\Servico\Models\Servico;
use App\Modules\Usuario\Models\Usuario;
use App\Modules\Venda\DTOs\VenderEtapasData;
use App\Modules\Venda\Models\{VendaEtapas, VendaProduto};
use App\Modules\Venda\Requests\CriarVendaRequest;
use App\Modules\Venda\Services\VendaService;
use App\Support\ContextoEmpresa;
use App\Traits\{DefineEmpresaDeCriacao, TratamentoErros};
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\View\View;

class VendaController extends Controller
{
    use DefineEmpresaDeCriacao;
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

            $formas = FormaPagamento::ativos()->orderBy('nome')->get();

            return view('venda::index', compact('vendas', 'filtros', 'atendentes', 'formas'));
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
            $clienteSelecionado = $clienteOld?->dadosParaCard();
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

            $formas = FormaPagamento::ativos()->orderBy('nome')->get();

            return view('venda::create', compact('atendentes', 'empresaId', 'clienteOld', 'clienteSelecionado', 'servicoOld', 'itensOld', 'formas'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de venda');
        }
    }

    public function store(CriarVendaRequest $request): RedirectResponse
    {
        try {
            /** @var Usuario $usuario */
            $usuario = $request->user();

            // Resolve a empresa da venda: empresa_id explicito (sub-seletor futuro) >
            // contexto da listagem (filtro/unica empresa acessivel) > empresa padrao do
            // usuario. Sem o fallback, um usuario com varias empresas acessiveis e sem
            // contexto selecionado gerava agendamento/venda sem empresa_id (viola NOT NULL).
            $empresaId = $request->filled('empresa_id')
                ? (int) $request->empresa_id
                : (ContextoEmpresa::resolver() ?? (int) $usuario->empresa_id);

            return $this->comEmpresaDeCriacao($empresaId, fn () => $this->processarVenda($request, $empresaId));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao registrar venda');
        }
    }

    /**
     * Converte os dados do request, valida pre-requisitos (caixa aberto para
     * vendas a vista) e delega a criacao ao service conforme o tipo de venda:
     * produto, servico em etapas ou agendamento unico.
     */
    private function processarVenda(CriarVendaRequest $request, int $empresaId): RedirectResponse
    {
        $condicao = CondicaoPagamento::from($request->condicao_pagamento);

        $forma = $request->filled('forma_pagamento')
            ? FormaPagamento::findOrFail((int) $request->forma_pagamento)
            : null;

        // Cartão quita o cliente na hora (vira recebível): trata como à-vista no
        // ledger do cliente, independentemente do que o form enviou. O nº de
        // parcelas informado é do cartão (agenda + taxa), não do cliente.
        $parcelasCartao = null;
        if ($forma && $forma->gera_recebivel) {
            $condicao = CondicaoPagamento::AVista;
            $parcelasCartao = $forma->permite_parcelas
                ? min(max(1, (int) $request->input('parcelas_cartao', 1)), $forma->max_parcelas ?? 1)
                : 1;
        }

        // Crediário: a loja financia o cliente → força "a prazo" (a receber do cliente),
        // espelho invertido do cartão. Não gera recebível de banco.
        if ($forma && $forma->tipo->forcaAPrazo()) {
            $condicao = CondicaoPagamento::APrazo;
        }

        $aVista = $condicao === CondicaoPagamento::AVista;

        $formaRecebimentoPrazo = $request->forma_recebimento_prazo
            ? FormaRecebimentoPrazo::from($request->forma_recebimento_prazo)
            : null;

        $numeroParcelas = $condicao->geraParcelas() ? (int) $request->numero_parcelas : null;
        // Crediário respeita o teto de parcelas do cliente configurado na forma.
        if ($numeroParcelas !== null && $forma && $forma->tipo->ehCrediario() && $forma->max_parcelas) {
            $numeroParcelas = min($numeroParcelas, $forma->max_parcelas);
        }
        $primeiroVencimento = $condicao->geraParcelas()
            ? Carbon::parse($request->primeiro_vencimento)
            : now();
        $mesReferencia = Carbon::parse($request->mes_referencia)->startOfMonth();

        $parcelasPersonalizadas = $this->extrairParcelasPersonalizadas($request);

        // Só a venda à-vista cujo dinheiro cai na gaveta (conta caixa) exige caixa aberto.
        // Cartão e PIX que caem em banco/carteira não dependem de caixa.
        $exigeCaixa = $aVista && $forma && $this->caixaService->exigeCaixaAberto($forma, $empresaId);
        if ($exigeCaixa && ! $this->caixaService->caixaAbertoDaEmpresa($empresaId, now()->toDateString())) {
            return redirect()->back()->withInput()
                ->with('erro', 'É necessário abrir o caixa de hoje desta empresa para registrar vendas à vista.');
        }

        if ($request->tipo_venda === 'produto') {
            $itens = $request->input('itens', []);

            // Form envia tudo como string ("cliente_id=422"); a assinatura do
            // service e ?int (strict_types). Casta preservando null (venda balcao).
            $clienteId = $request->filled('cliente_id') ? (int) $request->cliente_id : null;

            $this->service->criarVendaProduto(
                $clienteId,
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
                $parcelasCartao,
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
                $parcelasCartao,
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
                $parcelasCartao,
            );
            $msg = 'Agendamento criado com sucesso.';
        }

        return redirect()->route('vendas.index')->with('sucesso', $msg);
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
            $this->authorize('cancel', $vendaProduto);
            $this->service->cancelarVendaProduto($vendaProduto);

            return redirect()->route('vendas.index')->with('sucesso', 'Venda cancelada. Estoque devolvido e pagamento estornado.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao cancelar venda de produto');
        }
    }

    public function show(string $tipo, int $id): View|RedirectResponse
    {
        try {
            $venda = $this->service->detalhar($tipo, $id);
            $this->authorize('view', $venda->model);

            return view('venda::show', compact('venda'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao abrir detalhes da venda');
        }
    }

    public function excluirUnico(Agendamento $agendamento): RedirectResponse
    {
        try {
            $this->authorize('delete', $agendamento);
            $this->service->removerUnico($agendamento);

            return redirect()->route('vendas.index')->with('sucesso', 'Venda removida. Lançamentos desfeitos.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao remover venda de serviço único');
        }
    }

    public function excluirEtapas(VendaEtapas $etapas): RedirectResponse
    {
        try {
            $this->authorize('delete', $etapas);
            $this->service->removerEtapas($etapas);

            return redirect()->route('vendas.index')->with('sucesso', 'Venda removida. Lançamentos desfeitos.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao remover venda em etapas');
        }
    }

    public function excluirProduto(VendaProduto $vendaProduto): RedirectResponse
    {
        try {
            $this->authorize('delete', $vendaProduto);
            $this->service->removerVendaProduto($vendaProduto);

            return redirect()->route('vendas.index')->with('sucesso', 'Venda removida. Lançamentos desfeitos.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao remover venda de produto');
        }
    }

    public function recibo(string $tipo, int $id): Response|RedirectResponse
    {
        try {
            $dados = match ($tipo) {
                'unico' => $this->dadosReciboUnico($id),
                'etapas' => $this->dadosReciboEtapas($id),
                'produto' => $this->dadosReciboProduto($id),
                default => abort(404, 'Tipo de venda inválido'),
            };

            // A empresa do comprovante vem do proprio registro da venda (nao da
            // empresa-padrao de quem imprime) — ver 'empresa' em cada dadosRecibo*.
            $pdf = Pdf::loadView('venda::recibo', array_merge($dados, [
                'tipo' => $tipo,
            ]));

            return $pdf->stream("recibo-{$tipo}-{$id}.pdf");
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao gerar recibo');
        }
    }

    private function dadosReciboUnico(int $id): array
    {
        $agendamento = Agendamento::with(['empresa', 'cliente', 'servico', 'atendente', 'pagamento.parcelas.baixas'])->findOrFail($id);
        $pagamento = $agendamento->pagamento;
        $valor = $agendamento->servico->valor ?? 0;

        return [
            'empresa' => $agendamento->empresa,
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
        $etapas = VendaEtapas::with(['empresa', 'cliente', 'servico', 'atendente', 'agendamentos', 'pagamento.parcelas.baixas'])->findOrFail($id);
        $this->authorize('view', $etapas);
        $subtotal = $etapas->valor_total + $etapas->desconto - $etapas->acrescimo;

        return [
            'empresa' => $etapas->empresa,
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
        $venda = VendaProduto::with(['empresa', 'cliente', 'usuario', 'itens.produto', 'pagamento.parcelas.baixas'])->findOrFail($id);

        return [
            'empresa' => $venda->empresa,
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
