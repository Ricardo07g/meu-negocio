<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\DTOs\EmpresaData;
use App\Modules\Tenant\Models\Empresa;
use App\Modules\Tenant\Requests\SalvarEmpresaRequest;
use App\Modules\Tenant\Services\EmpresaService;
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

            $rede = auth()->user()->rede;
            $maxEmpresas = (int) ($rede->plano->max_empresas ?? 0);
            $atualEmpresas = $rede->empresas()->count();
            $limite = [
                'atual' => $atualEmpresas,
                'maximo' => $maxEmpresas,
                'atingido' => $maxEmpresas !== 0 && $atualEmpresas >= $maxEmpresas,
            ];

            return view('tenant::index', compact('empresas', 'limite'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar empresas');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Empresa::class);

            return view('tenant::create');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de empresa');
        }
    }

    public function store(SalvarEmpresaRequest $request): RedirectResponse
    {
        try {
            $rede = $request->user()->rede;
            $this->service->criar($rede, EmpresaData::from($request->validated()));

            return redirect()->route('empresas.index')->with('sucesso', 'Empresa criada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar empresa');
        }
    }

    public function edit(Empresa $empresa): View|RedirectResponse
    {
        try {
            $this->authorize('update', $empresa);

            return view('tenant::edit', compact('empresa'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de empresa');
        }
    }

    public function update(SalvarEmpresaRequest $request, Empresa $empresa): RedirectResponse
    {
        try {
            $this->authorize('update', $empresa);
            $this->service->atualizar($empresa, EmpresaData::from($request->validated()));

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
