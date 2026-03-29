<?php

namespace App\Modules\Papel\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PapelController extends Controller
{
    use TratamentoErros;

    public function index(): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Role::class);
            $papeis = Role::with('permissions')->get();

            return view('papel::index', compact('papeis'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar papéis');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Role::class);
            $permissoes = Permission::orderBy('name')->get()->groupBy(function ($p) {
                return explode('.', $p->name)[0];
            });

            return view('papel::create', compact('permissoes'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de papel');
        }
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $this->authorize('create', Role::class);

            $request->validate([
                'name' => ['required', 'string', 'max:50', 'unique:roles,name'],
                'permissoes' => ['nullable', 'array'],
                'permissoes.*' => ['string', 'exists:permissions,name'],
            ]);

            $papel = Role::create(['name' => $request->name, 'guard_name' => 'web']);
            $papel->syncPermissions($request->permissoes ?? []);

            return redirect()->route('papeis.index')->with('sucesso', 'Papel criado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar papel');
        }
    }

    public function edit(Role $papel): View|RedirectResponse
    {
        try {
            $this->authorize('update', $papel);
            $permissoes = Permission::orderBy('name')->get()->groupBy(function ($p) {
                return explode('.', $p->name)[0];
            });
            $papelPermissoes = $papel->permissions->pluck('name')->toArray();

            return view('papel::edit', compact('papel', 'permissoes', 'papelPermissoes'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de papel');
        }
    }

    public function update(Request $request, Role $papel): RedirectResponse
    {
        try {
            $this->authorize('update', $papel);

            if ($papel->name === 'Admin') {
                return back()->with('erro', 'O papel Admin não pode ser editado.');
            }

            $request->validate([
                'name' => ['required', 'string', 'max:50', 'unique:roles,name,' . $papel->id],
                'permissoes' => ['nullable', 'array'],
                'permissoes.*' => ['string', 'exists:permissions,name'],
            ]);

            $papel->update(['name' => $request->name]);
            $papel->syncPermissions($request->permissoes ?? []);

            return redirect()->route('papeis.index')->with('sucesso', 'Papel atualizado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar papel');
        }
    }

    public function destroy(Role $papel): RedirectResponse
    {
        try {
            $this->authorize('delete', $papel);

            if ($papel->name === 'Admin') {
                return back()->with('erro', 'O papel Admin não pode ser excluído.');
            }

            if ($papel->users()->count() > 0) {
                return back()->with('erro', 'Este papel possui usuários vinculados. Remova-os antes de excluir.');
            }

            $papel->delete();

            return redirect()->route('papeis.index')->with('sucesso', 'Papel excluído com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao excluir papel');
        }
    }
}
