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

    /** Cobertura minima para um canal valer uma acao de segmento. */
    private const COBERTURA_MINIMA_CANAL = 0.3;

    private const VERSAO_PROMPT = 'v8';

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
        - `resumo`: UMA frase com a leitura mais importante — a conclusao que muda a decisao de
          quem esta lendo. NAO repita totais ("a carteira tem 47 clientes e R$ 9.800") nem diga
          generalidades ("a distribuicao e desigual"): isso nao ajuda ninguem a decidir nada.
          Exemplo do nivel esperado, com numeros inventados: "Quase metade da sua receita esta
          em gente que parou de vir, entao recuperar parte disso rende mais que buscar cliente
          novo.".
        - `pontos_fortes`, `alertas`, `sugestoes`: exatamente 3 itens cada.
        - Cada item e uma frase COMPLETA, de 12 a 30 palavras, que cita o segmento de que fala.
          Fragmentos soltos como "Contate" ou "Concentracao de receita" NAO servem.
        - Em `alertas`, aponte RISCO: receita concentrada em poucos clientes, gente valiosa
          deixando de vir, base parada.
        - Em `sugestoes`, comece por um verbo no imperativo e diga o que fazer nesta semana. Nada
          de "e importante analisar" ou "e importante monitorar": isso nao e uma sugestao.
        - Ordene os itens do mais relevante para o menos relevante. **A primeira sugestao tem de
          atacar o grupo com mais receita em jogo** entre os que precisam de acao (em risco,
          inativos, eventuais) — nao adianta comecar por um grupo de R$ 100 quando ha um de
          R$ 3.000 parado. Nenhum grupo fica de fora por falta de canal: se `no_balcao` nao serve
          para ele, use WhatsApp.

        Canais — regra dura:
        - Em `canais` voce recebe cada canal com `usar: true` ou `usar: false`. **Use SOMENTE os
          que estao com `usar: true`.** Um canal com `usar: false` nao tem contato cadastrado
          suficiente: sugerir por ele e mandar o leitor fazer algo impossivel.
        - `no_balcao` significa falar com a pessoa no proximo atendimento — logo, so serve para
          quem ainda aparece (alto valor, recorrentes, recem-conquistados, eventuais). Nao use
          para "em risco" nem "inativos": eles nao vao aparecer, e por isso estao nesse grupo.
        - **Nunca recomende telefonema.** Para grupo grande soa invasivo e ninguem faz.
        - Nao sugira redes sociais: post nao alcanca um segmento especifico, alcanca quem passar.
        - Varie o verbo entre as tres sugestoes. Tres itens com o mesmo verbo e sinal de que voce
          nao pensou em cada um.

        Exemplos SO do nivel de detalhe esperado. Os numeros abaixo sao inventados e nao tem
        relacao com os seus: use exclusivamente os valores que voce recebeu.
        - alerta bom: "Os 47 clientes ocasionais respondem por cerca de R$ 28.000, mas voltam
          menos de duas vezes por ano."
        - alerta ruim: "Concentracao de receita."
        - sugestao boa: "Mande uma mensagem no WhatsApp aos 47 eventuais com uma condicao de
          retorno valida ate o fim do mes."
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
            'canais' => $this->canais($carteira),
            'receita_periodo_aprox' => $this->arredondar($carteira['receita_total'], 100),
            'ticket_medio_aprox' => $this->arredondar($carteira['ticket_medio'], 10),
            'moeda' => 'BRL',
            'segmentos' => $segmentos,
        ];
    }

    /**
     * Quais canais valem uma acao de segmento.
     *
     * A **decisao** vai pronta, so a contagem. Pedir ao modelo que conclua "1 e-mail em 47
     * clientes significa nao sugerir e-mail" e pedir raciocinio numerico — justamente o que ele
     * faz mal e o que esta arquitetura tira dele. A conta e do PHP; o texto e dele.
     */
    private function canais(array $carteira): array
    {
        $base = max(1, $carteira['total_clientes']);

        return [
            'whatsapp' => [
                'clientes' => $carteira['clientes_com_whatsapp'],
                'usar' => $carteira['clientes_com_whatsapp'] / $base >= self::COBERTURA_MINIMA_CANAL,
            ],
            'email' => [
                'clientes' => $carteira['clientes_com_email'],
                'usar' => $carteira['clientes_com_email'] / $base >= self::COBERTURA_MINIMA_CANAL,
            ],
            'no_balcao' => ['clientes' => $carteira['total_clientes'], 'usar' => true],
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
