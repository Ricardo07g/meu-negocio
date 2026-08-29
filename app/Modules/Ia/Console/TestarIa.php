<?php

declare(strict_types=1);

namespace App\Modules\Ia\Console;

use App\Modules\Ia\Contracts\Ia;
use App\Modules\Ia\DTOs\PedidoIa;
use App\Modules\Ia\Exceptions\IaIndisponivelException;
use Illuminate\Console\Command;

/**
 * Pre-voo da integracao: um round-trip real contra o provedor configurado.
 *
 * Fala com o **driver**, nao com o `AnaliseService` — de proposito. Aqui nao ha usuario
 * autenticado nem empresa em contexto, e o que se quer provar e exatamente uma coisa:
 * a credencial funciona e o modelo devolve JSON no formato pedido. Cota, cache e tenancy
 * sao problema da tela, nao do diagnostico.
 */
class TestarIa extends Command
{
    protected $signature = 'ia:testar {--modelo= : Testa outro modelo sem mexer no .env}';

    protected $description = 'Faz uma chamada real ao provedor de IA e mostra resposta, tokens, custo e latencia.';

    public function handle(Ia $ia): int
    {
        $driver = (string) config('ia.driver');

        // Override em memoria: os drivers leem `modelo()` do config a cada chamada, entao
        // isto basta para experimentar um modelo alternativo sem editar o .env.
        if ($modelo = $this->option('modelo')) {
            config(["ia.drivers.{$driver}.modelo" => $modelo]);
        }

        $this->line("Driver: <info>{$driver}</info>");
        $this->line("Modelo: <info>{$ia->modelo()}</info>");

        if (! $ia->estaAtivo()) {
            $this->error('Provedor NAO configurado — falta credencial no .env. A feature esta desligada.');

            return self::FAILURE;
        }

        $pedido = new PedidoIa(
            instrucoes: 'Voce responde em portugues do Brasil, de forma curta e direta. Responda apenas com o JSON pedido.',
            dados: ['pergunta' => 'Diga em uma frase o que e uma carteira de clientes.'],
            schema: [
                'type' => 'object',
                'properties' => ['resposta' => ['type' => 'string']],
                'required' => ['resposta'],
            ],
        );

        try {
            $resposta = $ia->analisar($pedido);
        } catch (IaIndisponivelException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $precos = (array) config("ia.drivers.{$driver}.precos", ['entrada' => 0, 'saida' => 0]);
        $custo = $resposta->custoEstimado((float) $precos['entrada'], (float) $precos['saida']);

        $this->newLine();
        $this->line('<info>Resposta:</info> '.json_encode($resposta->dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->newLine();

        $this->table(['Metrica', 'Valor'], [
            ['Tokens de entrada', $resposta->tokensEntrada],
            ['Tokens de saida', $resposta->tokensSaida],
            ['Tokens totais', $resposta->tokensTotais()],
            ['Custo estimado (USD)', '$'.number_format($custo, 6)],
            ['Latencia', $resposta->duracaoMs.' ms'],
        ]);

        $this->info('Pre-voo OK — credencial valida e resposta no formato pedido.');

        return self::SUCCESS;
    }
}
