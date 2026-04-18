<?php

namespace App\Modules\Despesa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Despesa\DTOs\DespesaData;
use App\Modules\Despesa\Requests\SalvarDespesaRequest;
use App\Modules\Despesa\Models\Despesa;
use App\Modules\Despesa\Services\DespesaService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DespesaController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private DespesaService $service,
    ) {}

    public function index(): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Despesa::class);
            $despesas = $this->service->listar();

            return view('despesa::index', compact('despesas'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar despesas');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Despesa::class);

            return view('despesa::create');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de despesa');
        }
    }

    public function store(SalvarDespesaRequest $request): RedirectResponse
    {
        try {
            $this->service->criar(DespesaData::from($request->validated()));

            return redirect()->route('despesas.index')->with('sucesso', 'Despesa criada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar despesa');
        }
    }

    public function edit(Despesa $despesa): View|RedirectResponse
    {
        try {
            $this->authorize('update', $despesa);

            return view('despesa::edit', compact('despesa'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de despesa');
        }
    }

    public function update(SalvarDespesaRequest $request, Despesa $despesa): RedirectResponse
    {
        try {
            $this->authorize('update', $despesa);
            $this->service->atualizar($despesa, DespesaData::from($request->validated()));

            return redirect()->route('despesas.index')->with('sucesso', 'Despesa atualizada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar despesa');
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
}
