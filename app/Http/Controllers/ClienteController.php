<?php

namespace App\Http\Controllers;

use App\DTO\Cliente\AtualizarClienteData;
use App\DTO\Cliente\CriarClienteData;
use App\Http\Requests\Cliente\AtualizarClienteRequest;
use App\Http\Requests\Cliente\CriarClienteRequest;
use App\Models\Cliente;
use App\Services\ClienteService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClienteController extends Controller
{
    use TratamentoErros;

    public function __construct(private ClienteService $service)
    {}

    public function index(): View|RedirectResponse
    {
        try
        {
            $this->authorize('viewAny', Cliente::class);
            $clientes = $this->service->listar();

            return view('clientes.index', compact('clientes'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar clientes');
        }
    }

    public function create(): View|RedirectResponse
    {
        try
        {
            $this->authorize('create', Cliente::class);

            return view('clientes.create');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de cliente');
        }
    }

    public function store(CriarClienteRequest $request): RedirectResponse
    {
        try 
        {
            $this->service->criar(CriarClienteData::from($request->validated()));

            return redirect()->route('clientes.index')->with('sucesso', 'Cliente criado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar cliente');
        }
    }

    public function show(Cliente $cliente): View|RedirectResponse
    {
        try 
        {
            $this->authorize('view', $cliente);

            $cliente->load([
                'vendasPacote.servico',
                'vendasPacote.profissional.usuario',
                'agendamentos.servico',
                'agendamentos.profissional.usuario',
                'pagamentos.agendamento.servico',
            ]);

            return view('clientes.show', compact('cliente'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir cliente');
        }
    }

    public function edit(Cliente $cliente): View|RedirectResponse
    {
        try 
        {
            $this->authorize('update', $cliente);

            return view('clientes.edit', compact('cliente'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de cliente');
        }
    }

    public function update(AtualizarClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        try {
            $this->authorize('update', $cliente);
            $this->service->atualizar($cliente, AtualizarClienteData::from($request->validated()));

            return redirect()->route('clientes.index')->with('sucesso', 'Cliente atualizado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar cliente');
        }
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        try {
            $this->authorize('delete', $cliente);
            $this->service->excluir($cliente);

            return redirect()->route('clientes.index')->with('sucesso', 'Cliente excluído com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao excluir cliente');
        }
    }
}
