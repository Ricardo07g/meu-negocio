<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\TratamentoErros;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Log, Password};
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
            //
            // O catch interno existe pelo mesmo motivo. Uma falha aqui (Resend fora
            // do ar, chave revogada, erro ao gravar o token) so acontece quando o
            // email EXISTE — para um endereco desconhecido o broker nem tenta enviar.
            // Deixar a excecao subir para o tratarErro devolveria tela de erro para
            // quem esta cadastrado e sucesso generico para quem nao esta: o proprio
            // vazamento que o paragrafo acima evita, so que pelo avesso. Entao
            // registramos o erro (visivel em `railway logs`) e respondemos igual.
            try {
                Password::sendResetLink($request->only('email'));
            } catch (\Throwable $e) {
                Log::error('Falha ao enviar o link de recuperacao de senha', [
                    'exception' => $e::class,
                    'mensagem' => $e->getMessage(),
                    'arquivo' => $e->getFile().':'.$e->getLine(),
                ]);
            }

            return back()->with(
                'sucesso',
                'Se o email informado estiver cadastrado, enviaremos um link de recuperação em instantes.'
            );
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao processar pedido de recuperação de senha');
        }
    }
}
