<?php

namespace App\Traits;

use App\Exceptions\NegocioException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

trait TratamentoErros
{
    protected function tratarErro(\Throwable $e, string $contexto): RedirectResponse|JsonResponse
    {
        if ($e instanceof ValidationException || $e instanceof AuthorizationException) 
        {
            throw $e;
        }

        if ($e instanceof NegocioException)
        {
            Log::warning($contexto, [
                'mensagem' => $e->getMessage(),
                'usuario_id' => auth()->id(),
                'exception' => $e::class,
            ]);
            $mensagem = $e->getMessage();
        } 
        else 
        {
            Log::error($contexto, [
                'mensagem' => $e->getMessage(),
                'usuario_id' => auth()->id(),
                'exception' => $e::class,
                'arquivo' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            $mensagem = 'Ocorreu um erro inesperado. Tente novamente.';
        }

        if (request()->ajax() || request()->wantsJson())
        {
            return response()->json(['erro' => $mensagem], 500);
        }

        return redirect()->back()->withInput()->with('erro', $mensagem);
    }
}
