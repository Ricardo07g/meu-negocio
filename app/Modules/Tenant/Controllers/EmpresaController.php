<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\DTOs\EmpresaData;
use App\Modules\Tenant\Models\Empresa;
use App\Modules\Tenant\Requests\SalvarEmpresaRequest;
use App\Modules\Tenant\Services\EmpresaService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Somente leitura + edicao cadastral. Contratar uma unidade (= contratar uma licenca)
 * e ato comercial do operador do SaaS, nao do tenant: `create`, `store` e `destroy`
 * saem do resource. `EmpresaService::criar`/`excluir` e `CriarEmpresaAction` seguem
 * intactos — sao a costura que o painel de superusuario vai consumir.
 */
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

            return view('tenant::index', compact('empresas'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar empresas');
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
}
