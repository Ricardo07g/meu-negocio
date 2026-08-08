<?php

declare(strict_types=1);

namespace App\Modules\Usuario\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Models\Empresa;
use App\Modules\Usuario\DTOs\UsuarioData;
use App\Modules\Usuario\Models\Usuario;
use App\Modules\Usuario\Requests\SalvarUsuarioRequest;
use App\Modules\Usuario\Services\UsuarioService;
use App\Support\PlanoVigente;
use App\Traits\{SincronizaImagemUnica, TratamentoErros};
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    use SincronizaImagemUnica, TratamentoErros;

    public function __construct(private UsuarioService $service) {}

    public function index(): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Usuario::class);
            $usuarios = $this->service->listar();

            // Assentos sao da licenca da unidade em contexto, nao da rede.
            $empresa = PlanoVigente::empresa();
            $maxUsuarios = (int) ($empresa?->plano->max_usuarios ?? 0);
            $atualUsuarios = $empresa?->contarUsuarios() ?? 0;
            $limite = [
                'atual' => $atualUsuarios,
                'maximo' => $maxUsuarios,
                'atingido' => $atualUsuarios >= $maxUsuarios,
            ];

            return view('usuario::index', compact('usuarios', 'limite'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar usuários');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Usuario::class);
            $papeis = Role::orderBy('name')->pluck('name');
            $empresas = Empresa::orderBy('nome')->get(['id', 'nome']);

            return view('usuario::create', compact('papeis', 'empresas'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de usuário');
        }
    }

    public function store(SalvarUsuarioRequest $request): RedirectResponse
    {
        try {
            $rede = $request->user()->rede;
            $usuario = $this->service->criar($rede, UsuarioData::from($request->validated()));
            $this->sincronizarImagem($usuario, $request);

            return redirect()->route('usuarios.index')->with('sucesso', 'Usuário criado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar usuário');
        }
    }

    public function edit(Usuario $usuario): View|RedirectResponse
    {
        try {
            $this->authorize('update', $usuario);
            $papeis = Role::orderBy('name')->pluck('name');
            $empresas = Empresa::orderBy('nome')->get(['id', 'nome']);
            $empresasSelecionadas = $usuario->empresas()->pluck('empresas.id')->all();

            return view('usuario::edit', compact('usuario', 'papeis', 'empresas', 'empresasSelecionadas'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de usuário');
        }
    }

    public function update(SalvarUsuarioRequest $request, Usuario $usuario): RedirectResponse
    {
        try {
            $this->authorize('update', $usuario);
            $usuario = $this->service->atualizar($usuario, UsuarioData::from($request->validated()));
            $this->sincronizarImagem($usuario, $request);

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
