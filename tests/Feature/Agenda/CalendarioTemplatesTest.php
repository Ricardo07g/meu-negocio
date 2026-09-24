<?php

declare(strict_types=1);

namespace Tests\Feature\Agenda;

use Tests\TestCase;

/**
 * Lint dos templates do Toast UI no `calendar.js` — nenhuma outra camada da
 * porta de qualidade le esse arquivo.
 *
 * Os templates da grade de horas (`timegridDisplayPrimaryTime`,
 * `timegridNowIndicatorLabel`, ...) recebem `{ time }` como TZDate da
 * biblioteca, nao como Date. O TZDate tem getHours()/getMinutes(), mas NAO
 * tem toLocaleTimeString()/toLocaleDateString(). Chamar um deles lanca dentro
 * do render: foi o que congelou a agenda na semana corrente — as setas mudavam
 * o titulo e a grade ficava parada, sem mostrar atendimento nenhum. Todo
 * teste HTTP continuava verde, porque o JSON sempre esteve certo.
 *
 * Nao substitui o clique real (`clique-agenda.cjs --navegacao`); barra a
 * armadilha conhecida, que e barata de ver por leitura.
 */
class CalendarioTemplatesTest extends TestCase
{
    /**
     * Arrow function que desestrutura `time` e, no corpo, chama `time.toLocale*`.
     */
    private const TZDATE_COM_METODO_DE_DATE = '/\(\s*\{[^}]*\btime\b[^}]*\}\s*\)\s*=>[^\n]*\btime\.toLocale\w*\s*\(/';

    /** @return list<string> as linhas que caem na armadilha */
    private function violacoes(string $js): array
    {
        $semComentarios = (string) preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', $js);

        preg_match_all(self::TZDATE_COM_METODO_DE_DATE, $semComentarios, $achados);

        return $achados[0];
    }

    public function test_lint_pega_o_template_que_congelou_a_agenda(): void
    {
        $regressao = "timegridNowIndicatorLabel: ({ time }) => time.toLocaleTimeString('pt-BR', { hour: '2-digit' }),";

        $this->assertCount(1, $this->violacoes($regressao), 'O lint deixou de reconhecer o defeito que ele existe para barrar.');
    }

    public function test_lint_aceita_os_metodos_que_o_tzdate_tem(): void
    {
        $valido = "timegridDisplayPrimaryTime: ({ time }) => `\${String(time.getHours()).padStart(2, '0')}:00`,";

        $this->assertSame([], $this->violacoes($valido));
    }

    public function test_templates_do_calendario_nao_chamam_metodo_de_date_no_tzdate(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/calendar.js');

        $this->assertSame(
            [],
            $this->violacoes($js),
            'Template do Toast UI chama toLocale*() no `time`, que e TZDate: o render lanca e a agenda congela. '
            .'Use getHours()/getMinutes() ou time.toDate().'
        );
    }
}
