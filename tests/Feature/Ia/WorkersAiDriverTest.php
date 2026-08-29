<?php

declare(strict_types=1);

namespace Tests\Feature\Ia;

use App\Modules\Ia\Drivers\WorkersAiIa;
use App\Modules\Ia\DTOs\PedidoIa;
use App\Modules\Ia\Exceptions\IaIndisponivelException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O driver da Cloudflare, com a rede falsificada.
 *
 * Existe por causa de dois bugs que a suite verde nao pegou e so apareceram na primeira
 * chamada real com payload de verdade — os dois estao travados aqui embaixo.
 */
class WorkersAiDriverTest extends TestCase
{
    private const URL = 'https://api.cloudflare.com/client/v4/accounts/conta-teste/ai/run/*';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ia.drivers.workers_ai.account_id' => 'conta-teste',
            'ia.drivers.workers_ai.token' => 'token-teste',
            'ia.drivers.workers_ai.modelo' => '@cf/meta/llama-3.3-70b-instruct-fp8-fast',
            'ia.drivers.workers_ai.url_base' => 'https://api.cloudflare.com/client/v4',
            'ia.max_tokens' => 1500,
        ]);
    }

    private function pedido(): PedidoIa
    {
        return new PedidoIa(
            instrucoes: 'Responda em portugues.',
            dados: ['clientes' => 10],
            schema: ['type' => 'object', 'properties' => ['resumo' => ['type' => 'string']]],
        );
    }

    public function test_sem_credencial_o_driver_se_declara_desligado(): void
    {
        config(['ia.drivers.workers_ai.token' => null]);

        $this->assertFalse((new WorkersAiIa)->estaAtivo());
    }

    /**
     * Regressao: sem `max_tokens` a Cloudflare aplica um default baixo e corta o JSON no meio
     * de uma string. O driver entao recusava por "formato invalido" e o sintoma nao apontava
     * para o tamanho. Nao apareceu com prompt curto; apareceu na primeira carteira real.
     */
    public function test_envia_max_tokens_e_o_schema_pedido(): void
    {
        Http::fake([self::URL => Http::response([
            'result' => ['response' => ['resumo' => 'ok'], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]],
            'success' => true,
        ])]);

        (new WorkersAiIa)->analisar($this->pedido());

        Http::assertSent(function ($request): bool {
            return $request['max_tokens'] === 1500
                && $request['response_format']['type'] === 'json_schema'
                && isset($request['response_format']['json_schema']['properties']['resumo'])
                && $request->hasHeader('Authorization', 'Bearer token-teste');
        });
    }

    public function test_resposta_em_string_json_tambem_e_aceita(): void
    {
        Http::fake([self::URL => Http::response([
            'result' => ['response' => '{"resumo":"veio como string"}', 'usage' => ['prompt_tokens' => 7, 'completion_tokens' => 3]],
            'success' => true,
        ])]);

        $resposta = (new WorkersAiIa)->analisar($this->pedido());

        $this->assertSame('veio como string', $resposta->dados['resumo']);
        $this->assertSame(7, $resposta->tokensEntrada);
        $this->assertSame(3, $resposta->tokensSaida);
    }

    /** JSON que abre e nao fecha e truncamento: a mensagem precisa dizer isso, nao "formato invalido". */
    public function test_json_truncado_avisa_sobre_max_tokens(): void
    {
        Http::fake([self::URL => Http::response([
            'result' => ['response' => '{"resumo":"comecou mas nao termin', 'usage' => []],
            'success' => true,
        ])]);

        $this->expectException(IaIndisponivelException::class);
        $this->expectExceptionMessageMatches('/cortada.*max_tokens/');

        (new WorkersAiIa)->analisar($this->pedido());
    }

    /** O texto do provedor entra na mensagem: "401" sozinho nao diz se e token ou permissao. */
    public function test_erro_do_provedor_aparece_na_mensagem(): void
    {
        Http::fake([self::URL => Http::response([
            'success' => false,
            'errors' => [['code' => 10000, 'message' => 'Authentication error']],
        ], 401)]);

        $this->expectException(IaIndisponivelException::class);
        $this->expectExceptionMessageMatches('/401.*Authentication error/');

        (new WorkersAiIa)->analisar($this->pedido());
    }

    public function test_falha_de_rede_vira_indisponibilidade_tratavel(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(IaIndisponivelException::class);

        (new WorkersAiIa)->analisar($this->pedido());
    }
}
