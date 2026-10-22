<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Usuario\Models\Usuario;
use App\Traits\TratamentoErros;
use Illuminate\Http\{RedirectResponse, Request};
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

    /**
     * Login em duas etapas quando o usuario tem 2FA (ADR-0022): a senha certa NAO
     * autentica — so registra a pendencia na sessao e manda para o desafio. Sem
     * 2FA, segue direto como antes.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        try {
            $credentials = $request->only('email', 'password');

            if (! Auth::validate($credentials)) {
                return back()->withErrors(['email' => 'Credenciais inválidas.'])->onlyInput('email');
            }

            /** @var Usuario $usuario */
            $usuario = Auth::getLastAttempted();

            if (! $usuario->ativo) {
                return back()->withErrors(['email' => 'Sua conta está desativada.']);
            }

            Auth::getProvider()->rehashPasswordIfRequired($usuario, $credentials);

            if ($usuario->temDoisFatoresAtivo()) {
                DesafioDoisFatoresController::registrarPendencia($request, $usuario);

                return redirect()->route('login.dois-fatores');
            }

            Auth::login($usuario);
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

            return redirect()->route('home');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao realizar logout');
        }
    }
}
