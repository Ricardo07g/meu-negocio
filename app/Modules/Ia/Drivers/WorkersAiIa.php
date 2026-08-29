<?php

declare(strict_types=1);

namespace App\Modules\Ia\Drivers;

use App\Modules\Ia\Contracts\Ia;
use App\Modules\Ia\DTOs\{PedidoIa, RespostaIa};
use App\Modules\Ia\Exceptions\IaIndisponivelException;
use Illuminate\Support\Facades\{Http, Log};
use Throwable;

/**
 * Cloudflare Workers AI — driver padrao.
 *
 * Reaproveita a conta Cloudflare que o projeto ja usa (R2, Turnstile, cron agendador).
 * Sem `token`/`account_id` configurados o driver se declara inativo e ninguem chama nada.
 *
 * **Sem retry, igual ao Turnstile.** O usuario esta olhando a tela: e melhor falhar em 30s
 * com aviso claro do que empilhar tentativas e devolver timeout de 90s. Diferente do
 * Turnstile, porem, aqui a falha NAO libera nada — so degrada a analise.
 */
class WorkersAiIa implements Ia
{
    public function estaAtivo(): bool
    {
        return filled($this->config('account_id')) && filled($this->config('token'));
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
            '%s/accounts/%s/ai/run/%s',
            rtrim((string) $this->config('url_base'), '/'),
            $this->config('account_id'),
            $this->modelo(),
        );

        try {
            $resposta = Http::withToken((string) $this->config('token'))
                ->timeout((int) config('ia.timeout'))
                ->asJson()
                ->post($url, [
                    'messages' => [
                        ['role' => 'system', 'content' => $pedido->instrucoes],
                        ['role' => 'user', 'content' => json_encode($pedido->dados, JSON_UNESCAPED_UNICODE)],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => $pedido->schema,
                    ],
                ]);
        } catch (Throwable $e) {
            Log::warning('Workers AI: falha de rede na analise.', ['exception' => $e]);

            throw new IaIndisponivelException('falha de comunicacao com o provedor', $e);
        }

        if ($resposta->failed()) {
            Log::warning('Workers AI: provedor respondeu erro.', [
                'status' => $resposta->status(),
                'corpo' => $resposta->json('errors'),
            ]);

            throw new IaIndisponivelException("o provedor respondeu {$resposta->status()}");
        }

        $duracao = (int) (microtime(true) * 1000) - $inicio;

        return new RespostaIa(
            dados: $this->extrairDados($resposta->json('result.response')),
            tokensEntrada: (int) $resposta->json('result.usage.prompt_tokens', 0),
            tokensSaida: (int) $resposta->json('result.usage.completion_tokens', 0),
            modelo: $this->modelo(),
            duracaoMs: $duracao,
        );
    }

    /**
     * Em JSON mode a Cloudflare costuma devolver o objeto ja decodificado, mas nem sempre —
     * dependendo do modelo vem string. Aceita os dois e recusa o resto.
     */
    private function extrairDados(mixed $resposta): array
    {
        if (is_array($resposta)) {
            return $resposta;
        }

        if (is_string($resposta)) {
            $decodificado = json_decode($resposta, true);

            if (is_array($decodificado)) {
                return $decodificado;
            }
        }

        throw new IaIndisponivelException('a resposta nao veio no formato pedido');
    }

    private function config(string $chave): mixed
    {
        return config("ia.drivers.workers_ai.{$chave}");
    }
}
