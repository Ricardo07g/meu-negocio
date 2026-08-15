<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\DTOs\EmpresaData;
use App\Modules\Tenant\Models\Empresa;
use App\Modules\Tenant\Requests\SalvarEmpresaRequest;
use App\Modules\Tenant\Services\{EmpresaService, ExpedienteService};
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
        private ExpedienteService $expediente,
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

            // Unidade antiga (anterior ao expediente) chega sem linha nenhuma:
            // semeia o padrao para a tela nunca abrir vazia.
            if ($this->expediente->daEmpresa($empresa->id)->isEmpty()) {
                $this->expediente->semearPadrao($empresa->rede_id, $empresa->id);
            }

            $expediente = $this->expediente->daEmpresa($empresa->id)->keyBy('dia_semana');

            return view('tenant::edit', compact('empresa', 'expediente'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de empresa');
        }
    }

    public function update(SalvarEmpresaRequest $request, Empresa $empresa): RedirectResponse
    {
        try {
            $this->authorize('update', $empresa);
            $this->service->atualizar($empresa, EmpresaData::from($request->safe()->except('expediente')));

            if ($request->has('expediente')) {
                $this->expediente->salvar($empresa, (array) $request->input('expediente'));
            }

            return redirect()->route('empresas.index')->with('sucesso', 'Empresa atualizada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar empresa');
        }
    }
}
