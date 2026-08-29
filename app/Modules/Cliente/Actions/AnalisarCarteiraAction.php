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

    private const VERSAO_PROMPT = 'v4';

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

        return $this->analises->analisar($empresa, TipoAnalise::CarteiraRfm, $this->montarPedido($carteira));
    }

    /**
     * Publico porque a TELA tambem precisa dele — nao para chamar o modelo, mas para comparar
     * o hash de hoje com o da analise guardada e avisar quando a carteira mudou desde entao.
     */
    public function montarPedido(array $carteira): PedidoIa
    {
        return new PedidoIa(
            instrucoes: $this->instrucoes(),
            dados: $this->payload($carteira),
            schema: $this->schema(),
            versaoPrompt: self::VERSAO_PROMPT,
        );
    }

    private function instrucoes(): string
    {
        return <<<'TXT'
        Voce e um consultor que ajuda donos de pequenos negocios (saloes, clinicas, autonomos) a
        entender a propria carteira de clientes.

        Voce recebe uma segmentacao RFM (Recencia, Frequencia, Valor) JA CALCULADA. Seu trabalho e
        INTERPRETAR. Nao calcule e nao devolva a tabela em texto: os numeros ja estao na tela, ao
        lado do seu texto. Repetir o que ele ja esta vendo nao ajuda ninguem.

        Formato:
        - `resumo`: UMA frase com o que mais chama atencao nesta carteira.
        - `pontos_fortes`, `alertas`, `sugestoes`: exatamente 3 itens cada.
        - Cada item e uma frase COMPLETA, de 12 a 30 palavras, que cita o segmento de que fala.
          Fragmentos soltos como "Contate" ou "Concentracao de receita" NAO servem.
        - Em `alertas`, aponte RISCO: receita concentrada em poucos clientes, gente valiosa
          deixando de vir, base parada.
        - Em `sugestoes`, comece por um verbo no imperativo e diga o que fazer nesta semana. Nada
          de "e importante analisar" ou "e importante monitorar": isso nao e uma sugestao.
        - Ordene os itens do mais relevante para o menos relevante.

        Exemplos SO do nivel de detalhe esperado. Os numeros abaixo sao inventados e nao tem
        relacao com os seus: use exclusivamente os valores que voce recebeu.
        - alerta bom: "Os 47 clientes ocasionais respondem por cerca de R$ 28.000, mas voltam
          menos de duas vezes por ano."
        - alerta ruim: "Concentracao de receita."
        - sugestao boa: "Ligue esta semana para os 47 eventuais oferecendo um horario de volta."
        - sugestao ruim: "Contate os clientes."

        Regras rigidas:
        - Portugues do Brasil, direto e sem jargao. O leitor nao sabe o que e "RFM".
        - NUNCA invente numero, nome de cliente, data ou percentual. Use so os valores recebidos.
        - Os valores monetarios sao aproximados: fale em "cerca de", nunca com precisao falsa.
        - Nao prometa resultado ("isso vai aumentar 30%").
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
                // Sem receita por segmento o modelo nao consegue apontar concentracao — e
                // concentracao e justamente o risco que vale a pena avisar.
                'receita_aprox' => $this->arredondar($s['receita'], 100),
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
                'sugestoes' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['resumo', 'pontos_fortes', 'alertas', 'sugestoes'],
        ];
    }
}
