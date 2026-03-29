<?php

namespace App\Http\Controllers;

use App\DTO\Agendamento\CriarAgendamentoData;
use App\DTO\Pacote\VenderPacoteData;
use App\Http\Requests\Venda\CriarVendaRequest;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Produto;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\VendaPacote;
use App\Models\VendaProduto;
use App\Services\VendaService;
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

            return view('vendas.index', compact('vendas'));
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
            $profissionais = Profissional::with('usuario')->get();
            $produtos = Produto::all();

            return view('vendas.create', compact('clientes', 'servicos', 'profissionais', 'produtos'));
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

            return view('vendas.show-produto', compact('vendaProduto'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir venda de produto');
        }
    }

    public function showAvulso(Agendamento $agendamento): View|RedirectResponse
    {
        try {
            $this->authorize('view', $agendamento);
            $agendamento->load(['cliente', 'servico', 'profissional.usuario', 'pagamento']);

            return view('vendas.show-avulso', compact('agendamento'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir venda avulsa');
        }
    }

    public function showPacote(VendaPacote $pacote): View|RedirectResponse
    {
        try {
            $this->authorize('view', $pacote);
            $pacote->load(['cliente', 'servico', 'profissional.usuario', 'agendamentos']);

            return view('vendas.show-pacote', compact('pacote'));
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
