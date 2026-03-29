<?php

namespace App\Modules\Venda\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Agenda\DTOs\CriarAgendamentoData;
use App\Modules\Venda\DTOs\VenderPacoteData;
use App\Modules\Venda\Requests\CriarVendaRequest;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Produto\Models\Produto;
use App\Modules\Servico\Models\Servico;
use App\Modules\Usuario\Models\Usuario;
use App\Modules\Venda\Models\VendaPacote;
use App\Modules\Venda\Models\VendaProduto;
use App\Modules\Venda\Services\VendaService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VendaController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private VendaService $service,
    ) {}

    public function index(): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Agendamento::class);
            $vendas = $this->service->listar();

            return view('venda::index', compact('vendas'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar vendas');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Agendamento::class);
            $clientes = Cliente::all();
            $servicos = Servico::all();
            $atendentes = Usuario::where('atende', true)->get();
            $produtos = Produto::all();

            return view('venda::create', compact('clientes', 'servicos', 'atendentes', 'produtos'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de venda');
        }
    }

    public function store(CriarVendaRequest $request): RedirectResponse
    {
        try {
            $formaPagamento = $request->forma_pagamento;
            $statusPagamento = $request->status_pagamento;

            if ($request->tipo_venda === 'produto') {
                $this->service->criarVendaProduto(
                    $request->cliente_id ?? 0,
                    $request->produto_id,
                    $request->quantidade,
                    $request->valor_total,
                    $formaPagamento,
                    $statusPagamento,
                );
                return redirect()->route('vendas.index')->with('sucesso', 'Produto vendido com sucesso.');
            }

            $servico = Servico::findOrFail($request->servico_id);

            if ($servico->isPacote()) {
                $this->service->criarPacote(VenderPacoteData::from($request->validated()), $formaPagamento, $statusPagamento);
                $msg = 'Pacote vendido com sucesso! Agendamentos criados.';
            } else {
                $this->service->criarAvulso(CriarAgendamentoData::from($request->validated()), $formaPagamento, $statusPagamento);
                $msg = 'Agendamento criado com sucesso.';
            }

            return redirect()->route('vendas.index')->with('sucesso', $msg);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao registrar venda');
        }
    }

    public function showProduto(VendaProduto $vendaProduto): View|RedirectResponse
    {
        try {
            $vendaProduto->load(['cliente', 'produto']);

            return view('venda::show-produto', compact('vendaProduto'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir venda de produto');
        }
    }

    public function showAvulso(Agendamento $agendamento): View|RedirectResponse
    {
        try {
            $this->authorize('view', $agendamento);
            $agendamento->load(['cliente', 'servico', 'atendente', 'pagamento']);

            return view('venda::show-avulso', compact('agendamento'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir venda avulsa');
        }
    }

    public function showPacote(VendaPacote $pacote): View|RedirectResponse
    {
        try {
            $this->authorize('view', $pacote);
            $pacote->load(['cliente', 'servico', 'atendente', 'agendamentos']);

            return view('venda::show-pacote', compact('pacote'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir pacote');
        }
    }

    public function cancelarAvulso(Agendamento $agendamento): RedirectResponse
    {
        try {
            $this->authorize('cancel', $agendamento);
            $this->service->cancelarAvulso($agendamento);

            return redirect()->route('vendas.index')->with('sucesso', 'Agendamento cancelado.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao cancelar venda avulsa');
        }
    }

    public function cancelarPacote(VendaPacote $pacote): RedirectResponse
    {
        try {
            $this->authorize('cancel', $pacote);
            $this->service->cancelarPacote($pacote);

            return redirect()->route('vendas.index')->with('sucesso', 'Pacote cancelado. Agendamentos pendentes foram cancelados.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao cancelar pacote');
        }
    }
}
