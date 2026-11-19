<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Detectores;

use Illuminate\Http\Request;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Ricardo07g\GuardaBot\Contratos\Detector;
use Ricardo07g\GuardaBot\Sinal;

/**
 * Quem a requisicao DIZ que e: User-Agent e o cabecalho `Signature-Agent` (Web
 * Bot Auth, usado por agentes de IA que se identificam).
 *
 * Pega so o que se declara. Um Playwright com UA de Chrome normal passa por
 * aqui sem sinal; ele e assunto de outros detectores e do desafio.
 */
class AgenteDeUsuario implements Detector
{
    /** Agentes e crawlers de empresas de IA que se identificam no UA. */
    private const AGENTES_IA = '/ChatGPT-User|OAI-SearchBot|GPTBot|Claude-User|Claude-Web|ClaudeBot|Claude-SearchBot|anthropic-ai|Perplexity-User|PerplexityBot|Google-Extended|Bytespider|Amazonbot|Applebot-Extended|cohere-ai|meta-externalagent|meta-externalfetcher|MistralAI-User|DuckAssistBot/i';

    /** Ferramentas de automacao e clientes HTTP de linguagem. */
    private const AUTOMACAO = '/HeadlessChrome|PhantomJS|SlimerJS|Selenium|WebDriver|Puppeteer|Playwright|curl\/|Wget\/|python-requests|python-urllib|python-httpx|aiohttp|Go-http-client|okhttp|Java\/|Apache-HttpClient|libwww-perl|axios\/|node-fetch|undici|Scrapy/i';

    public function avaliar(Request $request): array
    {
        if ($request->headers->has('Signature-Agent')) {
            return [new Sinal('agente_ia_declarado')];
        }

        $agente = trim((string) $request->userAgent());

        return match (true) {
            $agente === '' => [new Sinal('sem_user_agent')],
            preg_match(self::AGENTES_IA, $agente) === 1 => [new Sinal('agente_ia_declarado')],
            preg_match(self::AUTOMACAO, $agente) === 1 => [new Sinal('automacao_declarada')],
            (new CrawlerDetect([], $agente))->isCrawler($agente) => [new Sinal('crawler')],
            default => [],
        };
    }
}
