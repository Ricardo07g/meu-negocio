<?php

declare(strict_types=1);

namespace App\Modules\Cliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Arquivo\Services\ArquivoService;
use App\Modules\Cliente\DTOs\ClienteData;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Cliente\Requests\SalvarClienteRequest;
use App\Modules\Cliente\Services\ClienteService;
use App\Traits\TratamentoErros;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\View\View;

class ClienteController extends Controller
{
    use TratamentoErros;

    public function __construct(private ClienteService $service, private ArquivoService $arquivos) {}

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Cliente::class);
            $filtros = $request->only(['q', 'situacao_financeira', 'atividade', 'aniversariantes', 'com_whatsapp']);
            $clientes = $this->service->listar($filtros);

            return view('cliente::index', compact('clientes', 'filtros'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar clientes');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Cliente::class);

            return view('cliente::create');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de cliente');
        }
    }

    public function store(SalvarClienteRequest $request): RedirectResponse
    {
        try {
            $cliente = $this->service->criar(ClienteData::from($request->validated()));
            $this->arquivos->sincronizarUnico($cliente, 'avatar', $request->file('foto'), $request->boolean('remover_foto'));

            return redirect()->route('clientes.index')->with('sucesso', 'Cliente criado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar cliente');
        }
    }

    public function show(Cliente $cliente): View|RedirectResponse
    {
        try {
            $this->authorize('view', $cliente);

            $cliente->load([
                'vendasEtapas.servico',
                'vendasEtapas.atendente',
                'agendamentos.servico',
                'agendamentos.atendente',
                'pagamentos.agendamento.servico',
            ]);

            return view('cliente::show', compact('cliente'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir cliente');
        }
    }

    public function edit(Cliente $cliente): View|RedirectResponse
    {
        try {
            $this->authorize('update', $cliente);

            return view('cliente::edit', compact('cliente'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de cliente');
        }
    }

    public function update(SalvarClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        try {
            $this->authorize('update', $cliente);
            $cliente = $this->service->atualizar($cliente, ClienteData::from($request->validated()));
            $this->arquivos->sincronizarUnico($cliente, 'avatar', $request->file('foto'), $request->boolean('remover_foto'));

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

    public function buscar(Request $request): JsonResponse
    {
        $q = $request->query('q', '');

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $clientes = Cliente::where('nome', 'like', "%{$q}%")
            ->orWhere('telefone', 'like', "%{$q}%")
            ->with('arquivoPrincipal')
            ->limit(10)
            ->get()
            ->map(fn (Cliente $c) => $c->dadosParaCard());

        return response()->json($clientes);
    }
}
