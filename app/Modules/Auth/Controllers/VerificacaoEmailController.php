<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\TratamentoErros;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Tela "confirme seu e-mail" e o link assinado. Quem nao verificou cai aqui pelo
 * middleware `verified:verificacao.aviso` em todo o grupo autenticado (ADR-0022).
 */
class VerificacaoEmailController extends Controller
{
    use TratamentoErros;

    public function aviso(Request $request): View|RedirectResponse
    {
        try {
            if ($request->user()->hasVerifiedEmail()) {
                return redirect()->route('dashboard');
            }

            return view('auth::verificar-email', ['email' => $request->user()->email]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar a confirmação de e-mail');
        }
    }

    /**
     * EmailVerificationRequest ja confere que o {id} e o usuario logado e que o
     * {hash} bate com o e-mail ATUAL — link de um e-mail antigo nao vale. A
     * assinatura e a validade ficam com o middleware `signed`.
     */
    public function verificar(EmailVerificationRequest $request): RedirectResponse
    {
        try {
            $request->fulfill();

            return redirect()->route('dashboard')->with('sucesso', 'E-mail confirmado. Bem-vindo ao Meu Negócio!');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao confirmar e-mail');
        }
    }

    public function reenviar(Request $request): RedirectResponse
    {
        try {
            $usuario = $request->user();

            if ($usuario->hasVerifiedEmail()) {
                return redirect()->route('dashboard');
            }

            $usuario->enviarVerificacaoSemFalhar();

            return back()->with('sucesso', 'Enviamos um novo link de confirmação para o seu e-mail.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao reenviar a confirmação de e-mail');
        }
    }
}
