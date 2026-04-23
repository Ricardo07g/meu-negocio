<?php

namespace App\Modules\Papel\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Papel\Requests\SalvarPapelRequest;
use App\Modules\Papel\Services\PapelService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class PapelController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private PapelService $service,
    ) {}

    public function index(): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Role::class);
            $papeis = $this->service->listar();

            return view('papel::index', compact('papeis'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar papéis');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Role::class);
            $permissoes = $this->service->permissoesAgrupadas();

            return view('papel::create', compact('permissoes'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de papel');
        }
    }

    public function store(SalvarPapelRequest $request): RedirectResponse
    {
        try {
            $this->service->criar(
                $request->validated('name'),
                $request->validated('permissoes') ?? [],
            );

            return redirect()->route('papeis.index')->with('sucesso', 'Papel criado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar papel');
        }
    }

    public function show(Role $papel): View|RedirectResponse
    {
        try {
            $this->authorize('view', $papel);
            $papel->load('permissions');

            return view('papel::show', compact('papel'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir papel');
        }
    }

    public function edit(Role $papel): View|RedirectResponse
    {
        try {
            $this->authorize('update', $papel);
            $permissoes = $this->service->permissoesAgrupadas();
            $papelPermissoes = $papel->permissions->pluck('name')->toArray();

            return view('papel::edit', compact('papel', 'permissoes', 'papelPermissoes'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de papel');
        }
    }

    public function update(SalvarPapelRequest $request, Role $papel): RedirectResponse
    {
        try {
            $this->service->atualizar(
                $papel,
                $request->validated('name'),
                $request->validated('permissoes') ?? [],
            );

            return redirect()->route('papeis.index')->with('sucesso', 'Papel atualizado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar papel');
        }
    }

    public function destroy(Role $papel): RedirectResponse
    {
        try {
            $this->authorize('delete', $papel);
            $this->service->excluir($papel);

            return redirect()->route('papeis.index')->with('sucesso', 'Papel excluído com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao excluir papel');
        }
    }
}
