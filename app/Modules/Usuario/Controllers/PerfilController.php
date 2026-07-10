<?php

declare(strict_types=1);

namespace App\Modules\Usuario\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Arquivo\Services\ArquivoService;
use App\Modules\Usuario\Requests\{AtualizarPerfilRequest, AtualizarSenhaPerfilRequest};
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PerfilController extends Controller
{
    use TratamentoErros;

    public function __construct(private ArquivoService $arquivos) {}

    public function index(): View|RedirectResponse
    {
        try {
            $usuario = auth()->user()->loadMissing([
                'empresa:id,nome',
                'empresas:id,nome',
                'roles:id,name',
            ]);

            return view('usuario::perfil', compact('usuario'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar perfil');
        }
    }

    public function atualizar(AtualizarPerfilRequest $request): RedirectResponse
    {
        try {
            $usuario = $request->user();
            $usuario->update($request->safe()->only(['nome', 'email']));
            $this->arquivos->sincronizarUnico($usuario, 'avatar', $request->file('foto'), $request->boolean('remover_foto'));

            return redirect()->route('perfil.index')->with('sucesso', 'Perfil atualizado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar perfil');
        }
    }

    public function atualizarSenha(AtualizarSenhaPerfilRequest $request): RedirectResponse
    {
        try {
            $usuario = $request->user();
            $usuario->update([
                'password' => Hash::make($request->input('password')),
            ]);

            return redirect()->route('perfil.index')->with('sucesso', 'Senha alterada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao alterar senha');
        }
    }
}
