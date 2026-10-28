<?php

declare(strict_types=1);

namespace App\Traits;

use App\Exceptions\NegocioException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\{JsonResponse, RedirectResponse};
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

trait TratamentoErros
{
    /**
     * Segredos nunca voltam no withInput(): senha e codigo iriam parar no payload
     * da sessao (tabela `sessions`) so para repreencher um campo que nem usa old().
     * Mesma ideia do `dontFlash` do handler de excecoes, que so vale para validacao.
     * `codigo` NAO entra: e o SKU do form de Produto (e o TOTP do 2FA vale 30s e
     * nao se reusa — devolve-lo nao expoe nada).
     *
     * @return list<string>
     */
    protected static function camposQueNaoVoltam(): array
    {
        return ['password', 'password_confirmation', 'senha_atual', 'senha_2fa', 'codigo_recuperacao'];
    }

    protected function tratarErro(\Throwable $e, string $contexto): RedirectResponse|JsonResponse
    {
        if ($e instanceof ValidationException || $e instanceof AuthorizationException) {
            throw $e;
        }

        if ($e instanceof NegocioException) {
            Log::warning($contexto, [
                'mensagem' => $e->getMessage(),
                'usuario_id' => auth()->id(),
                'exception' => $e::class,
            ]);
            $mensagem = $e->getMessage();
        } else {
            Log::error($contexto, [
                'mensagem' => $e->getMessage(),
                'usuario_id' => auth()->id(),
                'exception' => $e::class,
                'arquivo' => $e->getFile().':'.$e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            $mensagem = 'Ocorreu um erro inesperado. Tente novamente.';
        }

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['erro' => $mensagem], 500);
        }

        return redirect()->back()->withInput(request()->except(self::camposQueNaoVoltam()))->with('erro', $mensagem);
    }
}
