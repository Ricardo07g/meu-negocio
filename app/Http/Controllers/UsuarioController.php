<?php

namespace App\Http\Controllers;

use App\DTO\Usuario\AtualizarUsuarioData;
use App\DTO\Usuario\CriarUsuarioData;
use App\Http\Requests\Usuario\AtualizarUsuarioRequest;
use App\Http\Requests\Usuario\CriarUsuarioRequest;
use App\Models\Usuario;
use App\Services\UsuarioService;
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

            return view('usuarios.index', compact('usuarios'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar usuários');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Usuario::class);
            $papeis = Role::orderBy('name')->pluck('name');

            return view('usuarios.create', compact('papeis'));
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

            return view('usuarios.show', compact('usuario'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir usuário');
        }
    }

    public function edit(Usuario $usuario): View|RedirectResponse
    {
        try {
            $this->authorize('update', $usuario);
            $papeis = Role::orderBy('name')->pluck('name');

            return view('usuarios.edit', compact('usuario', 'papeis'));
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
