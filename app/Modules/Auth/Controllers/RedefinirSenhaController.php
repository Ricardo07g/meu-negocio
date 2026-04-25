<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Usuario\Models\Usuario;
use App\Traits\TratamentoErros;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RedefinirSenhaController extends Controller
{
    use TratamentoErros;

    public function showResetForm(Request $request, string $token): View|RedirectResponse
    {
        try {
            return view('auth::redefinir-senha', [
                'token' => $token,
                'email' => $request->query('email'),
            ]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar página de redefinição de senha');
        }
    }

    public function reset(Request $request): RedirectResponse
    {
        try {
            $request->validate([
                'token' => ['required'],
                'email' => ['required', 'email'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (Usuario $usuario, string $password) {
                    $usuario->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    event(new PasswordReset($usuario));
                }
            );

            if ($status === Password::PasswordReset) {
                return redirect()->route('login')->with(
                    'sucesso',
                    'Senha redefinida com sucesso. Faça login com a nova senha.'
                );
            }

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao redefinir senha');
        }
    }
}
