<?php

declare(strict_types=1);

namespace App\Modules\Ia\Drivers;

use App\Modules\Ia\Contracts\Ia;
use App\Modules\Ia\DTOs\{PedidoIa, RespostaIa};
use App\Modules\Ia\Exceptions\IaIndisponivelException;

/**
 * Driver da suite de testes: responde payload fixo e **nunca toca a rede**.
 *
 * E o driver de `phpunit.xml` (IA_DRIVER=fake), o que garante que nenhum teste
 * dependa de credencial, de internet ou do humor de um provedor externo.
 *
 * Os setters estaticos existem para o teste dirigir o cenario (cota, erro, conteudo);
 * `resetar()` roda no setUp da trait de teste.
 */
class FakeIa implements Ia
{
    public static array $dados = [
        'resumo' => 'Analise de exemplo.',
        'pontos_fortes' => [],
        'alertas' => [],
        'sugestoes' => [],
    ];

    public static int $tokensEntrada = 100;

    public static int $tokensSaida = 50;

    public static bool $ativo = true;

    /** Quando setado, `analisar()` falha com este motivo — cobre o caminho de indisponibilidade. */
    public static ?string $falharCom = null;

    /** Quantas vezes o provedor foi realmente chamado. E assim que o teste de cache prova o ganho. */
    public static int $chamadas = 0;

    public static function resetar(): void
    {
        self::$dados = [
            'resumo' => 'Analise de exemplo.',
            'pontos_fortes' => [],
            'alertas' => [],
            'sugestoes' => [],
        ];
        self::$tokensEntrada = 100;
        self::$tokensSaida = 50;
        self::$ativo = true;
        self::$falharCom = null;
        self::$chamadas = 0;
    }

    public function estaAtivo(): bool
    {
        return self::$ativo;
    }

    public function modelo(): string
    {
        return 'fake';
    }

    public function analisar(PedidoIa $pedido): RespostaIa
    {
        self::$chamadas++;

        if (self::$falharCom !== null) {
            throw new IaIndisponivelException(self::$falharCom);
        }

        return new RespostaIa(
            dados: self::$dados,
            tokensEntrada: self::$tokensEntrada,
            tokensSaida: self::$tokensSaida,
            modelo: $this->modelo(),
            duracaoMs: 1,
        );
    }
}
