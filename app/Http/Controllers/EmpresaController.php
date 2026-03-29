<?php

namespace App\Http\Controllers;

use App\DTO\Empresa\AtualizarEmpresaData;
use App\DTO\Empresa\CriarEmpresaData;
use App\Http\Requests\Empresa\AtualizarEmpresaRequest;
use App\Http\Requests\Empresa\CriarEmpresaRequest;
use App\Models\Empresa;
use App\Services\EmpresaService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmpresaController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private EmpresaService $service,
    ) {}

    public function index(): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Empresa::class);
            $empresas = $this->service->listar();

            return view('empresas.index', compact('empresas'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar empresas');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Empresa::class);

            return view('empresas.create');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de empresa');
        }
    }

    public function store(CriarEmpresaRequest $request): RedirectResponse
    {
        try {
            $rede = $request->user()->rede;
            $this->service->criar($rede, CriarEmpresaData::from($request->validated()));

            return redirect()->route('empresas.index')->with('sucesso', 'Empresa criada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar empresa');
        }
    }

    public function show(Empresa $empresa): View|RedirectResponse
    {
        try {
            $this->authorize('view', $empresa);

            return view('empresas.show', compact('empresa'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir empresa');
        }
    }

    public function edit(Empresa $empresa): View|RedirectResponse
    {
        try {
            $this->authorize('update', $empresa);

            return view('empresas.edit', compact('empresa'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de empresa');
        }
    }

    public function update(AtualizarEmpresaRequest $request, Empresa $empresa): RedirectResponse
    {
        try {
            $this->authorize('update', $empresa);
            $this->service->atualizar($empresa, AtualizarEmpresaData::from($request->validated()));

            return redirect()->route('empresas.index')->with('sucesso', 'Empresa atualizada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar empresa');
        }
    }

    public function destroy(Empresa $empresa): RedirectResponse
    {
        try {
            $this->authorize('delete', $empresa);
            $this->service->excluir($empresa);

            return redirect()->route('empresas.index')->with('sucesso', 'Empresa excluída com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao excluir empresa');
        }
    }
}
