<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Tests\Detectores;

use PHPUnit\Framework\Attributes\DataProvider;
use Ricardo07g\GuardaBot\Detectores\AgenteDeUsuario;
use Ricardo07g\GuardaBot\Sinal;
use Ricardo07g\GuardaBot\Tests\TestCase;

class AgenteDeUsuarioTest extends TestCase
{
    /**
     * @param  array<string, string>  $cabecalhos
     * @return list<string>
     */
    private function motivos(array $cabecalhos): array
    {
        return array_map(
            fn (Sinal $s): string => $s->motivo,
            (new AgenteDeUsuario)->avaliar($this->requisicao($cabecalhos)),
        );
    }

    public function test_navegador_comum_nao_gera_sinal(): void
    {
        $this->assertSame([], $this->motivos(['User-Agent' => self::CHROME]));
    }

    public function test_requisicao_sem_user_agent_e_sinalizada(): void
    {
        $this->assertSame(['sem_user_agent'], $this->motivos([]));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ferramentasDeAutomacao(): array
    {
        return [
            'headless chrome' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/142.0.0.0 Safari/537.36'],
            'curl' => ['curl/8.5.0'],
            'python requests' => ['python-requests/2.31.0'],
            'go' => ['Go-http-client/2.0'],
            'phantomjs' => ['Mozilla/5.0 (Unknown; Linux x86_64) AppleWebKit/538.1 (KHTML, like Gecko) PhantomJS/2.1.1 Safari/538.1'],
        ];
    }

    #[DataProvider('ferramentasDeAutomacao')]
    public function test_ferramenta_de_automacao_declarada_e_sinalizada(string $agente): void
    {
        $this->assertSame(['automacao_declarada'], $this->motivos(['User-Agent' => $agente]));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function agentesDeIa(): array
    {
        return [
            'chatgpt' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot'],
            'claude' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Claude-User/1.0; +Claude-User@anthropic.com)'],
            'perplexity' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Perplexity-User/1.0; +https://perplexity.ai/perplexity-user)'],
            'gptbot' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)'],
        ];
    }

    #[DataProvider('agentesDeIa')]
    public function test_agente_de_ia_declarado_no_user_agent_e_sinalizado(string $agente): void
    {
        $this->assertSame(['agente_ia_declarado'], $this->motivos(['User-Agent' => $agente]));
    }

    public function test_agente_que_assina_com_web_bot_auth_e_sinalizado_mesmo_com_ua_de_chrome(): void
    {
        $this->assertSame(
            ['agente_ia_declarado'],
            $this->motivos(['User-Agent' => self::CHROME, 'Signature-Agent' => '"https://chatgpt.com"']),
        );
    }

    public function test_crawler_conhecido_e_sinalizado(): void
    {
        $this->assertSame(
            ['crawler'],
            $this->motivos(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']),
        );
    }
}
