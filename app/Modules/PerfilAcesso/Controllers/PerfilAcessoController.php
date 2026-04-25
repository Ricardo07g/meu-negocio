<?php

namespace App\Modules\PerfilAcesso\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PerfilAcesso\Requests\SalvarPerfilAcessoRequest;
use App\Modules\PerfilAcesso\Services\PerfilAcessoService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class PerfilAcessoController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private PerfilAcessoService $service,
    ) {}

    public function index(): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Role::class);
            $perfis = $this->service->listar();

            return view('perfilacesso::index', compact('perfis'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar perfis de acesso');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Role::class);
            $permissoes = $this->service->permissoesAgrupadas();

            return view('perfilacesso::create', compact('permissoes'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de perfil de acesso');
        }
    }

    public function store(SalvarPerfilAcessoRequest $request): RedirectResponse
    {
        try {
            $this->service->criar(
                $request->validated('name'),
                $request->validated('permissoes') ?? [],
            );

            return redirect()->route('perfis-acesso.index')->with('sucesso', 'Perfil de acesso criado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar perfil de acesso');
        }
    }

    public function show(Role $perfilAcesso): View|RedirectResponse
    {
        try {
            $this->authorize('view', $perfilAcesso);
            $perfilAcesso->load('permissions');

            return view('perfilacesso::show', compact('perfilAcesso'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir perfil de acesso');
        }
    }

    public function edit(Role $perfilAcesso): View|RedirectResponse
    {
        try {
            $this->authorize('update', $perfilAcesso);
            $permissoes = $this->service->permissoesAgrupadas();
            $perfilPermissoes = $perfilAcesso->permissions->pluck('name')->toArray();

            return view('perfilacesso::edit', compact('perfilAcesso', 'permissoes', 'perfilPermissoes'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de perfil de acesso');
        }
    }

    public function update(SalvarPerfilAcessoRequest $request, Role $perfilAcesso): RedirectResponse
    {
        try {
            $this->service->atualizar(
                $perfilAcesso,
                $request->validated('name'),
                $request->validated('permissoes') ?? [],
            );

            return redirect()->route('perfis-acesso.index')->with('sucesso', 'Perfil de acesso atualizado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar perfil de acesso');
        }
    }

    public function destroy(Role $perfilAcesso): RedirectResponse
    {
        try {
            $this->authorize('delete', $perfilAcesso);
            $this->service->excluir($perfilAcesso);

            return redirect()->route('perfis-acesso.index')->with('sucesso', 'Perfil de acesso excluído com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao excluir perfil de acesso');
        }
    }
}
