<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\LoginRequest;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    use TratamentoErros;

    public function showLoginForm(): View|RedirectResponse
    {
        try {
            return view('auth::login');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar página de login');
        }
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        try {
            $credentials = $request->only('email', 'password');

            if (! Auth::attempt($credentials)) {
                return back()->withErrors(['email' => 'Credenciais inválidas.'])->onlyInput('email');
            }

            $usuario = Auth::user();

            if (! $usuario->ativo) {
                Auth::logout();

                return back()->withErrors(['email' => 'Sua conta está desativada.']);
            }

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao realizar login');
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        try {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao realizar logout');
        }
    }
}
