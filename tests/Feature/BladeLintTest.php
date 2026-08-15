<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lint das views Blade — o ponto cego da porta de qualidade.
 *
 * O Pint pula `.blade.php`, o PHPStan nao le template e os testes Feature batem
 * em HTTP, nao no JS que roda no navegador. Um botao pode abrir o modal, nao
 * submeter nada e mesmo assim deixar a suite verde: foi o que aconteceu com a
 * troca de plano da assinatura, que nunca funcionou.
 *
 * Este teste nao substitui um clique real — ele barra as armadilhas conhecidas,
 * que sao baratas de detectar por leitura. O mesmo conjunto de regras roda como
 * hook ao editar (`.claude/hooks/blade-lint.sh`), avisando na hora; aqui e onde
 * o PR trava se passar batido.
 *
 * Precisao importa mais que cobertura: um lint que grita a toa e um lint que
 * alguem desliga. Por isso comentarios sao removidos antes da analise e
 * interpolacao de `route()`/`asset()` nao conta como risco.
 */
class BladeLintTest extends TestCase
{
    /**
     * Funcoes cujo retorno nao contem aspas nem apostrofos — interpolar o
     * resultado delas dentro de aspas simples de JS e seguro.
     */
    private const INTERPOLACAO_SEGURA = ['route', 'asset', 'url', 'config', 'csrf_token', 'old', '__', 'trans'];

    /** Views do projeto: modulos + views compartilhadas. */
    public static function views(): iterable
    {
        $raiz = dirname(__DIR__, 2);

        foreach ([$raiz.'/app/Modules', $raiz.'/resources/views'] as $diretorio) {
            if (! is_dir($diretorio)) {
                continue;
            }

            $arquivos = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($diretorio, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($arquivos as $arquivo) {
                if (! str_ends_with($arquivo->getFilename(), '.blade.php')) {
                    continue;
                }

                $relativo = str_replace($raiz.'/', '', $arquivo->getPathname());
                yield $relativo => [$arquivo->getPathname(), $relativo];
            }
        }
    }

    /**
     * Remove comentarios Blade e HTML: exemplo em comentario nao e codigo, e
     * contar diretiva citada em prosa e a receita do falso positivo.
     */
    private function semComentarios(string $caminho): string
    {
        $conteudo = (string) file_get_contents($caminho);
        $conteudo = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $conteudo);

        return (string) preg_replace('/<!--.*?-->/s', '', $conteudo);
    }

    /**
     * O SweetAlert2 embarcado no Duralux e anterior ao `isConfirmed`: resolve com
     * `{value: true}`. Ler so o campo novo faz o handler nunca disparar — sem erro
     * no console, o que torna a falha invisivel em revisao.
     */
    #[DataProvider('views')]
    public function test_confirmacao_do_sweetalert_nao_depende_so_de_is_confirmed(string $caminho, string $relativo): void
    {
        $conteudo = $this->semComentarios($caminho);

        if (! str_contains($conteudo, 'isConfirmed')) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertStringContainsString(
            '.value',
            $conteudo,
            "{$relativo} usa `isConfirmed`, que nao existe no SweetAlert2 deste projeto, sem aceitar "
            .'tambem `result.value`. O modal abre e o botao nao faz nada. Use `result.value` ou aceite as duas formas.'
        );
    }

    /**
     * `text: '{{ $msg }}'` escapa as aspas da mensagem como `&quot;` e quebra o
     * script inteiro quando o texto tem apostrofo. `@json(...)` resolve os dois.
     *
     * Interpolar `route()`/`asset()` assim e seguro e continua liberado: o risco
     * mora em texto livre (mensagem, nome digitado, observacao).
     */
    #[DataProvider('views')]
    public function test_interpolacao_de_texto_em_js_nao_usa_aspas_simples(string $caminho, string $relativo): void
    {
        $conteudo = $this->semComentarios($caminho);

        preg_match_all("/'\{\{(.+?)\}\}'/s", $conteudo, $ocorrencias);

        $arriscadas = array_filter(
            $ocorrencias[1] ?? [],
            fn (string $expressao) => ! $this->ehInterpolacaoSegura($expressao)
        );

        $this->assertSame(
            [],
            array_values(array_map('trim', $arriscadas)),
            "{$relativo} interpola texto dentro de aspas simples de JS. Isso exibe `&quot;` no lugar das "
            .'aspas e quebra o script se o texto tiver apostrofo. Use @json(...).'
        );
    }

    private function ehInterpolacaoSegura(string $expressao): bool
    {
        $expressao = trim($expressao);

        foreach (self::INTERPOLACAO_SEGURA as $funcao) {
            if (preg_match('/^'.preg_quote($funcao, '/').'\s*\(/', $expressao) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Diretiva aberta e nao fechada normalmente estoura so ao abrir a tela.
     * Contagem simples: pega o esquecimento, nao valida aninhamento.
     */
    #[DataProvider('views')]
    public function test_diretivas_blade_estao_balanceadas(string $caminho, string $relativo): void
    {
        $conteudo = $this->semComentarios($caminho);

        // `@auth`/`@guest` valem sem parenteses; o resto sempre abre com `(`.
        $pares = [
            'if' => ['endif', true],
            'foreach' => ['endforeach', true],
            'forelse' => ['endforelse', true],
            'push' => ['endpush', true],
            'can' => ['endcan', true],
            'unless' => ['endunless', true],
            'isset' => ['endisset', true],
            'auth' => ['endauth', false],
            'guest' => ['endguest', false],
        ];

        foreach ($pares as $abre => [$fecha, $exigeParenteses]) {
            $padraoAbertura = $exigeParenteses
                ? "/@{$abre}\s*\(/"
                : "/@{$abre}\b(?!\w)/";

            $aberturas = preg_match_all($padraoAbertura, $conteudo);
            $fechamentos = preg_match_all("/@{$fecha}\b/", $conteudo);

            $this->assertSame(
                $aberturas,
                $fechamentos,
                "{$relativo}: @{$abre} x @{$fecha} desbalanceados ({$aberturas} vs {$fechamentos})."
            );
        }
    }
}
