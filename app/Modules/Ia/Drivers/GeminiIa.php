<?php

declare(strict_types=1);

namespace App\Modules\Ia\Drivers;

use App\Modules\Ia\Contracts\Ia;
use App\Modules\Ia\DTOs\{PedidoIa, RespostaIa};
use App\Modules\Ia\Exceptions\IaIndisponivelException;
use Illuminate\Support\Facades\{Http, Log};
use Throwable;

/**
 * Google Gemini — escape hatch do Workers AI.
 *
 * Existe por um motivo concreto: o `responseSchema` do Gemini e imposto pelo servidor,
 * enquanto a Cloudflare so tenta cumprir o schema. Se o portugues do Llama ou a aderencia
 * ao formato decepcionarem, `IA_DRIVER=gemini` troca o provedor sem tocar em mais nada —
 * e essa e a prova de que a abstracao vale o que custou.
 */
class GeminiIa implements Ia
{
    public function estaAtivo(): bool
    {
        return filled($this->config('chave'));
    }

    public function modelo(): string
    {
        return (string) $this->config('modelo');
    }

    public function analisar(PedidoIa $pedido): RespostaIa
    {
        if (! $this->estaAtivo()) {
            throw new IaIndisponivelException('provedor nao configurado');
        }

        $inicio = (int) (microtime(true) * 1000);

        $url = sprintf(
            '%s/models/%s:generateContent',
            rtrim((string) $this->config('url_base'), '/'),
            $this->modelo(),
        );

        try {
            $resposta = Http::withHeaders(['x-goog-api-key' => (string) $this->config('chave')])
                ->timeout((int) config('ia.timeout'))
                ->asJson()
                ->post($url, [
                    'system_instruction' => ['parts' => [['text' => $pedido->instrucoes]]],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => json_encode($pedido->dados, JSON_UNESCAPED_UNICODE)]],
                    ]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema' => $pedido->schema,
                        'maxOutputTokens' => (int) config('ia.max_tokens'),
                        'temperature' => (float) config('ia.temperatura'),
                    ],
                ]);
        } catch (Throwable $e) {
            Log::warning('Gemini: falha de rede na analise.', ['exception' => $e]);

            throw new IaIndisponivelException('falha de comunicacao com o provedor', $e);
        }

        if ($resposta->failed()) {
            Log::warning('Gemini: provedor respondeu erro.', [
                'status' => $resposta->status(),
                'corpo' => $resposta->json('error.message'),
            ]);

            throw new IaIndisponivelException("o provedor respondeu {$resposta->status()}");
        }

        $texto = $resposta->json('candidates.0.content.parts.0.text');
        $dados = is_string($texto) ? json_decode($texto, true) : null;

        if (! is_array($dados)) {
            throw new IaIndisponivelException('a resposta nao veio no formato pedido');
        }

        return new RespostaIa(
            dados: $dados,
            tokensEntrada: (int) $resposta->json('usageMetadata.promptTokenCount', 0),
            tokensSaida: (int) $resposta->json('usageMetadata.candidatesTokenCount', 0),
            modelo: $this->modelo(),
            duracaoMs: (int) (microtime(true) * 1000) - $inicio,
        );
    }

    private function config(string $chave): mixed
    {
        return config("ia.drivers.gemini.{$chave}");
    }
}
