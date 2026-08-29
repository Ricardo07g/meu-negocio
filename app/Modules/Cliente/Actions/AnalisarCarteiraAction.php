<?php

declare(strict_types=1);

namespace App\Modules\Cliente\Actions;

use App\Modules\Cliente\Services\SegmentacaoRfmService;
use App\Modules\Ia\DTOs\PedidoIa;
use App\Modules\Ia\Enums\TipoAnalise;
use App\Modules\Ia\Exceptions\DadosInsuficientesException;
use App\Modules\Ia\Models\AnaliseIa;
use App\Modules\Ia\Services\AnaliseService;
use App\Modules\Tenant\Models\Empresa;

/**
 * Traduz a segmentacao RFM em um pedido para o modelo.
 *
 * A divisao de trabalho e o ponto: o SQL ja classificou tudo, e o modelo so nomeia,
 * explica e sugere acao. Ele nao recebe uma unica data nem um id — recebe contagens e
 * medias arredondadas.
 */
class AnalisarCarteiraAction
{
    /** Abaixo disso a analise vira texto generico e cobra igual — melhor nem chamar. */
    public const MINIMO_CLIENTES = 5;

    private const VERSAO_PROMPT = 'v1';

    public function __construct(
        private readonly SegmentacaoRfmService $rfm,
        private readonly AnaliseService $analises,
    ) {}

    public function executar(Empresa $empresa, int $meses = 12): AnaliseIa
    {
        $carteira = $this->rfm->segmentar($meses);

        if ($carteira['clientes_com_compra'] < self::MINIMO_CLIENTES) {
            throw new DadosInsuficientesException(
                'Ainda ha poucos clientes com compras no periodo para uma analise util. '
                .'Sao necessarios pelo menos '.self::MINIMO_CLIENTES.'.'
            );
        }

        return $this->analises->analisar($empresa, TipoAnalise::CarteiraRfm, new PedidoIa(
            instrucoes: $this->instrucoes(),
            dados: $this->payload($carteira),
            schema: $this->schema(),
            versaoPrompt: self::VERSAO_PROMPT,
        ));
    }

    private function instrucoes(): string
    {
        return <<<'TXT'
        Voce e um consultor de negocios que ajuda donos de pequenos negocios (saloes, clinicas,
        autonomos) a entender a propria carteira de clientes.

        Voce recebe uma segmentacao RFM (Recencia, Frequencia, Valor) JA CALCULADA. Seu trabalho
        e interpretar, nao calcular.

        Regras rigidas:
        - Escreva em portugues do Brasil, direto e sem jargao. O leitor nao sabe o que e "RFM".
        - NUNCA invente numero, nome de cliente, data ou percentual. Use apenas os valores recebidos.
        - Os valores monetarios recebidos sao aproximados: fale em "cerca de", nunca com precisao falsa.
        - Nao prometa resultado ("isso vai aumentar 30%"). Sugira acoes concretas e possiveis.
        - Cada item deve caber em uma frase.
        - Se um segmento estiver vazio, nao comente sobre ele.
        TXT;
    }

    /**
     * O payload que vira hash de cache — por isso e **todo inteiro**.
     *
     * Se entrasse aqui um ticket medio de R$ 147,32, qualquer venda mudaria o hash e o cache
     * nunca acertaria. Arredondar preserva o cache sem prejudicar o texto: a tela mostra o
     * numero exato, vindo do SQL; o modelo so precisa da ordem de grandeza para narrar.
     */
    private function payload(array $carteira): array
    {
        $segmentos = collect($carteira['segmentos'])
            ->filter(fn (array $s): bool => $s['clientes'] > 0)
            ->map(fn (array $s): array => [
                'segmento' => $s['label'],
                'significado' => $s['descricao'],
                'clientes' => $s['clientes'],
                'percentual' => (int) round($s['percentual']),
                'ticket_medio_aprox' => $this->arredondar($s['ticket_medio'], 10),
            ])
            ->values()
            ->all();

        return [
            'periodo_meses' => $carteira['periodo_meses'],
            'total_clientes' => $carteira['total_clientes'],
            'clientes_com_compra' => $carteira['clientes_com_compra'],
            'clientes_sem_compra' => $carteira['clientes_sem_compra'],
            'receita_periodo_aprox' => $this->arredondar($carteira['receita_total'], 100),
            'ticket_medio_aprox' => $this->arredondar($carteira['ticket_medio'], 10),
            'moeda' => 'BRL',
            'segmentos' => $segmentos,
        ];
    }

    private function arredondar(float $valor, int $passo): int
    {
        return (int) (round($valor / $passo) * $passo);
    }

    /** Achatado de proposito: a Cloudflare recusa schema aninhado complexo. */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'resumo' => ['type' => 'string'],
                'pontos_fortes' => ['type' => 'array', 'items' => ['type' => 'string']],
                'alertas' => ['type' => 'array', 'items' => ['type' => 'string']],
                'acoes' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['resumo', 'pontos_fortes', 'alertas', 'acoes'],
        ];
    }
}
