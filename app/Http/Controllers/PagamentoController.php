<?php

namespace App\Http\Controllers;

use App\DTO\Pagamento\RegistrarPagamentoData;
use App\Http\Requests\Pagamento\RegistrarPagamentoRequest;
use App\Models\Pagamento;
use App\Services\PagamentoService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PagamentoController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private PagamentoService $service,
    ) {}

    public function index(): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Pagamento::class);
            $pagamentos = $this->service->listar();

            return view('pagamentos.index', compact('pagamentos'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar pagamentos');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Pagamento::class);

            return view('pagamentos.create');
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

    public function show(Pagamento $pagamento): View|RedirectResponse
    {
        try {
            $this->authorize('view', $pagamento);
            $pagamento->load('agendamento.cliente');

            return view('pagamentos.show', compact('pagamento'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir pagamento');
        }
    }
}
