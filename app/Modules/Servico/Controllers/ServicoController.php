<?php

namespace App\Modules\Servico\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Servico\DTOs\AtualizarServicoData;
use App\Modules\Servico\DTOs\CriarServicoData;
use App\Modules\Servico\Requests\AtualizarServicoRequest;
use App\Modules\Servico\Requests\CriarServicoRequest;
use App\Modules\Servico\Models\Servico;
use App\Modules\Servico\Services\ServicoService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServicoController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private ServicoService $service,
    ) {}

    public function index(): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Servico::class);
            $servicos = $this->service->listar();

            return view('servico::index', compact('servicos'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar serviços');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Servico::class);

            return view('servico::create');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de serviço');
        }
    }

    public function store(CriarServicoRequest $request): RedirectResponse
    {
        try {
            $this->service->criar(CriarServicoData::from($request->validated()));

            return redirect()->route('servicos.index')->with('sucesso', 'Serviço criado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar serviço');
        }
    }

    public function show(Servico $servico): View|RedirectResponse
    {
        try {
            $this->authorize('view', $servico);

            return view('servico::show', compact('servico'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir serviço');
        }
    }

    public function edit(Servico $servico): View|RedirectResponse
    {
        try {
            $this->authorize('update', $servico);

            return view('servico::edit', compact('servico'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de serviço');
        }
    }

    public function update(AtualizarServicoRequest $request, Servico $servico): RedirectResponse
    {
        try {
            $this->authorize('update', $servico);
            $this->service->atualizar($servico, AtualizarServicoData::from($request->validated()));

            return redirect()->route('servicos.index')->with('sucesso', 'Serviço atualizado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar serviço');
        }
    }

    public function destroy(Servico $servico): RedirectResponse
    {
        try {
            $this->authorize('delete', $servico);
            $this->service->excluir($servico);

            return redirect()->route('servicos.index')->with('sucesso', 'Serviço excluído com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao excluir serviço');
        }
    }
}
