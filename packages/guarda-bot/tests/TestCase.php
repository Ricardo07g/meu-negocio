<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Tests;

use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase as Orchestra;
use Ricardo07g\GuardaBot\Detectores\Armadilha;
use Ricardo07g\GuardaBot\GuardaBotServiceProvider;

abstract class TestCase extends Orchestra
{
    protected const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36';

    protected function getPackageProviders($app): array
    {
        return [GuardaBotServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('guarda-bot.modo', 'observar');
    }

    /**
     * Cabecalhos de um Chrome real num POST de formulario sobre HTTPS.
     *
     * @return array<string, string>
     */
    protected function cabecalhosDeNavegador(string $agente = self::CHROME): array
    {
        return [
            'User-Agent' => $agente,
            'Accept-Language' => 'pt-BR,pt;q=0.9',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-Mode' => 'navigate',
        ];
    }

    /**
     * Campos da armadilha como o componente os renderiza, num formulario aberto
     * ha `$segundosAtras` segundos.
     *
     * @return array<string, string>
     */
    protected function armadilha(int $segundosAtras = 30, string $honeypot = ''): array
    {
        return [
            (string) config('guarda-bot.armadilha.campo') => $honeypot,
            (string) config('guarda-bot.armadilha.campo_tempo') => app(Armadilha::class)
                ->carimbo(now()->subSeconds($segundosAtras)->getTimestampMs()),
        ];
    }

    /**
     * Request montado a mao para testar detectores sem passar por rota.
     *
     * @param  array<string, string>  $cabecalhos
     * @param  array<string, string>  $dados
     */
    protected function requisicao(array $cabecalhos, array $dados = [], string $metodo = 'POST', bool $https = true): Request
    {
        $server = $https ? ['HTTPS' => 'on'] : [];

        foreach ($cabecalhos as $nome => $valor) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $nome))] = $valor;
        }

        $request = Request::create('/formulario', $metodo, $dados, server: $server);

        // O Symfony preenche User-Agent e Accept-Language por padrao; aqui o
        // teste decide exatamente o que o "cliente" enviou.
        foreach (['User-Agent', 'Accept-Language'] as $padrao) {
            if (! array_key_exists($padrao, $cabecalhos)) {
                $request->headers->remove($padrao);
            }
        }

        return $request;
    }
}
