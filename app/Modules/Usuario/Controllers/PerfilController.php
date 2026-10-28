<?php

declare(strict_types=1);

namespace App\Modules\Usuario\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\DoisFatoresService;
use App\Modules\Usuario\Requests\{AtualizarPerfilRequest, AtualizarSenhaPerfilRequest};
use App\Traits\{SincronizaImagemUnica, TratamentoErros};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PerfilController extends Controller
{
    use SincronizaImagemUnica, TratamentoErros;

    public function index(DoisFatoresService $doisFatores): View|RedirectResponse
    {
        try {
            $usuario = auth()->user()->loadMissing([
                'empresa:id,nome',
                'empresas:id,nome',
                'roles:id,name',
                'rede:id,exigir_2fa',
            ]);

            // QR so existe durante a configuracao (segredo gravado, ainda nao confirmado).
            $qrCode = $usuario->two_factor_secret !== null && ! $usuario->temDoisFatoresAtivo()
                ? $doisFatores->qrCodeSvg($usuario)
                : null;

            return view('usuario::perfil', compact('usuario', 'qrCode'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar perfil');
        }
    }

    public function atualizar(AtualizarPerfilRequest $request): RedirectResponse
    {
        try {
            $usuario = $request->user();
            $usuario->update($request->safe()->only(['nome', 'email']));
            $usuario->revalidarEmailSeAlterado();
            $this->sincronizarImagem($usuario, $request);

            if (! $usuario->hasVerifiedEmail()) {
                return redirect()->route('verificacao.aviso')
                    ->with('sucesso', 'Perfil atualizado. Confirme o novo e-mail pelo link que enviamos.');
            }

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
