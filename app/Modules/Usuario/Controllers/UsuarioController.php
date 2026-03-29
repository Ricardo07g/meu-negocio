<?php

namespace App\Modules\Usuario\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Usuario\DTOs\AtualizarUsuarioData;
use App\Modules\Usuario\DTOs\CriarUsuarioData;
use App\Modules\Usuario\Requests\AtualizarUsuarioRequest;
use App\Modules\Usuario\Requests\CriarUsuarioRequest;
use App\Modules\Usuario\Models\Usuario;
use App\Modules\Usuario\Services\UsuarioService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private UsuarioService $service,
    ) {}

    public function index(): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Usuario::class);
            $usuarios = $this->service->listar();

            return view('usuario::index', compact('usuarios'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar usuários');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Usuario::class);
            $plano = auth()->user()->rede->plano;
            $papeis = $plano->permiteGerenciarPapeis()
                ? Role::where('name', '!=', 'Admin')->orderBy('name')->pluck('name')
                : collect(['Profissional']);

            return view('usuario::create', compact('papeis'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de usuário');
        }
    }

    public function store(CriarUsuarioRequest $request): RedirectResponse
    {
        try {
            $rede = $request->user()->rede;
            $this->service->criar($rede, CriarUsuarioData::from($request->validated()));

            return redirect()->route('usuarios.index')->with('sucesso', 'Usuário criado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar usuário');
        }
    }

    public function show(Usuario $usuario): View|RedirectResponse
    {
        try {
            $this->authorize('view', $usuario);

            return view('usuario::show', compact('usuario'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir usuário');
        }
    }

    public function edit(Usuario $usuario): View|RedirectResponse
    {
        try {
            $this->authorize('update', $usuario);
            $plano = auth()->user()->rede->plano;
            $papeis = $plano->permiteGerenciarPapeis()
                ? Role::where('name', '!=', 'Admin')->orderBy('name')->pluck('name')
                : collect(['Profissional']);

            return view('usuario::edit', compact('usuario', 'papeis'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de usuário');
        }
    }

    public function update(AtualizarUsuarioRequest $request, Usuario $usuario): RedirectResponse
    {
        try {
            $this->authorize('update', $usuario);
            $this->service->atualizar($usuario, AtualizarUsuarioData::from($request->validated()));

            return redirect()->route('usuarios.index')->with('sucesso', 'Usuário atualizado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar usuário');
        }
    }

    public function destroy(Usuario $usuario): RedirectResponse
    {
        try {
            $this->authorize('delete', $usuario);
            $this->service->excluir($usuario);

            return redirect()->route('usuarios.index')->with('sucesso', 'Usuário excluído com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao excluir usuário');
        }
    }
}
