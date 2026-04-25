<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class EsqueciSenhaController extends Controller
{
    use TratamentoErros;

    public function showLinkRequestForm(): View|RedirectResponse
    {
        try {
            return view('auth::esqueci-senha');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar página de recuperação de senha');
        }
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        try {
            $request->validate([
                'email' => ['required', 'email'],
            ]);

            // Independente do retorno (email existe ou nao), exibimos a mesma
            // mensagem generica — boa pratica de seguranca para nao vazar
            // existencia de cadastro.
            Password::sendResetLink($request->only('email'));

            return back()->with(
                'sucesso',
                'Se o email informado estiver cadastrado, enviaremos um link de recuperação em instantes.'
            );
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao processar pedido de recuperação de senha');
        }
    }
}
