<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A automacao de `.claude/` tratada como codigo: testada, nao so escrita.
 *
 * As rules sao lidas pela IA como fonte autoritativa. Uma que aponte para um
 * arquivo que nao existe mais nao e um typo — e instrucao errada entregue com
 * a mesma confianca da instrucao certa. E um `paths:` que nao casa com nada e
 * pior ainda: a rule simplesmente nunca carrega, sem qualquer sinal disso.
 *
 * Este e o **nivel 1** dos evals: deterministico, sem LLM, roda no CI a cada
 * push. Os niveis 2 (triggering) e 3 (A/B com grader) vivem em `evals/`, custam
 * dinheiro e rodam sob demanda — veja `evals/README.md`.
 */
class AutomacaoIntegridadeTest extends TestCase
{
    private static function raiz(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return array<string, mixed> */
    private function frontmatter(string $caminho): array
    {
        $conteudo = (string) file_get_contents($caminho);

        if (! preg_match('/\A---\R(.*?)\R---\R/s', $conteudo, $m)) {
            return [];
        }

        $dados = [];
        $chaveLista = null;

        foreach (preg_split('/\R/', $m[1]) ?: [] as $linha) {
            if (preg_match('/^\s*-\s*(.+)$/', $linha, $item) && $chaveLista !== null) {
                $dados[$chaveLista][] = trim($item[1], " \"'");

                continue;
            }

            if (preg_match('/^([a-zA-Z_]+):\s*(.*)$/', $linha, $par)) {
                $chave = $par[1];
                $valor = trim($par[2]);

                if ($valor === '') {
                    $chaveLista = $chave;
                    $dados[$chave] = [];
                } else {
                    $chaveLista = null;
                    $dados[$chave] = trim($valor, " \"'");
                }
            }
        }

        return $dados;
    }

    public static function rules(): iterable
    {
        $base = self::raiz().'/.claude/rules';

        foreach (glob($base.'/*.md') ?: [] as $arquivo) {
            yield basename($arquivo) => [$arquivo];
        }
        foreach (glob($base.'/modulos/*.md') ?: [] as $arquivo) {
            yield 'modulos/'.basename($arquivo) => [$arquivo];
        }
    }

    public static function skills(): iterable
    {
        foreach (glob(self::raiz().'/.claude/skills/*/SKILL.md') ?: [] as $arquivo) {
            yield basename(dirname($arquivo)) => [$arquivo];
        }
    }

    public static function agentesEComandos(): iterable
    {
        foreach (glob(self::raiz().'/.claude/agents/*.md') ?: [] as $arquivo) {
            yield 'agents/'.basename($arquivo) => [$arquivo, 'agent'];
        }
        foreach (glob(self::raiz().'/.claude/commands/*.md') ?: [] as $arquivo) {
            yield 'commands/'.basename($arquivo) => [$arquivo, 'command'];
        }
    }

    /**
     * Sem `paths:` a rule nao tem gatilho e vira documentacao morta — exatamente
     * o que a pasta `.ai/` era antes de virar rules.
     */
    #[DataProvider('rules')]
    public function test_rule_declara_paths(string $caminho): void
    {
        $frontmatter = $this->frontmatter($caminho);
        $nome = basename($caminho);

        $this->assertArrayHasKey('paths', $frontmatter, "{$nome} nao declara `paths:` no frontmatter — nunca sera carregada.");
        $this->assertNotEmpty($frontmatter['paths'], "{$nome} tem `paths:` vazio.");
    }

    /**
     * O caso silencioso: `paths:` que nao casa com arquivo nenhum. A rule existe,
     * parece saudavel, e nunca entra em contexto. Costuma acontecer quando um
     * modulo e renomeado e a rule fica apontando para o caminho antigo.
     */
    #[DataProvider('rules')]
    public function test_paths_da_rule_alcancam_arquivos_reais(string $caminho): void
    {
        $frontmatter = $this->frontmatter($caminho);
        $nome = basename($caminho);

        foreach ((array) ($frontmatter['paths'] ?? []) as $padrao) {
            // `**` do glob-de-rule = qualquer profundidade; o glob do PHP nao faz
            // isso nativamente, entao viramos regex sobre a lista de arquivos.
            $regex = '#^'.str_replace(
                ['\*\*/', '\*\*', '\*'],
                ['(?:.*/)?', '.*', '[^/]*'],
                preg_quote($padrao, '#')
            ).'$#';

            $encontrou = false;
            foreach ($this->arquivosDoRepo() as $relativo) {
                if (preg_match($regex, $relativo) === 1) {
                    $encontrou = true;
                    break;
                }
            }

            $this->assertTrue(
                $encontrou,
                "{$nome}: o padrao `{$padrao}` nao casa com nenhum arquivo do repo — a rule nunca carrega por esse caminho."
            );
        }
    }

    /** @return list<string> */
    private function arquivosDoRepo(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $raiz = self::raiz();
        exec('cd '.escapeshellarg($raiz).' && git ls-files', $saida);

        return $cache = $saida;
    }

    /**
     * Rule que cita arquivo inexistente manda a IA procurar o que nao ha — e,
     * pior, sugere um caminho errado com ar de autoridade.
     */
    #[DataProvider('rules')]
    public function test_arquivos_citados_pela_rule_existem(string $caminho): void
    {
        $conteudo = (string) file_get_contents($caminho);
        $nome = basename($caminho);

        preg_match_all('/`([a-zA-Z0-9_][a-zA-Z0-9_.\/-]*\.(?:php|md|js|cjs|json|sh|neon|xml))`/', $conteudo, $m);

        $inexistentes = [];
        foreach (array_unique($m[1] ?? []) as $citado) {
            // So checa caminhos com diretorio: `BaseModel.php` solto e referencia
            // a uma classe, nao a um caminho de arquivo.
            if (! str_contains($citado, '/')) {
                continue;
            }
            // Caminhos com placeholder ({modulo}, Xxx) sao ilustrativos.
            if (preg_match('/[{}]|Xxx|<[a-z]/', $citado) === 1) {
                continue;
            }

            if (! $this->existeNoRepo($citado)) {
                $inexistentes[] = $citado;
            }
        }

        $this->assertSame([], $inexistentes, "{$nome} cita arquivo(s) que nao existem: ".implode(', ', $inexistentes));
    }

    /**
     * As rules citam caminhos como o leitor os escreveria — `modulos/caixa.md`
     * dentro de `.claude/rules/`, `layouts/app.blade.php` dentro de
     * `resources/views/`. Exigir caminho completo so geraria ruido; o que
     * interessa e o arquivo ter sumido do repo inteiro.
     */
    private function existeNoRepo(string $citado): bool
    {
        if (file_exists(self::raiz().'/'.$citado)) {
            return true;
        }

        foreach ($this->arquivosDoRepo() as $relativo) {
            if (str_ends_with($relativo, '/'.$citado)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A `description` e o unico texto sempre visivel da skill: e por ela que o
     * modelo decide invocar ou ignorar. Sem gatilho explicito ("use quando..."),
     * a skill existe e nunca dispara.
     */
    #[DataProvider('skills')]
    public function test_skill_tem_frontmatter_util(string $caminho): void
    {
        $frontmatter = $this->frontmatter($caminho);
        $diretorio = basename(dirname($caminho));

        $this->assertArrayHasKey('name', $frontmatter, "skill {$diretorio}: falta `name`.");
        $this->assertSame(
            $diretorio,
            $frontmatter['name'],
            "skill {$diretorio}: `name` ({$frontmatter['name']}) difere do diretorio — a invocacao usa o diretorio."
        );

        $this->assertArrayHasKey('description', $frontmatter, "skill {$diretorio}: falta `description`.");
        $descricao = (string) $frontmatter['description'];

        $this->assertGreaterThan(
            60,
            strlen($descricao),
            "skill {$diretorio}: `description` curta demais para o modelo decidir quando usar."
        );
        $this->assertMatchesRegularExpression(
            '/\b(use|usar|consulte|acione)\b/i',
            $descricao,
            "skill {$diretorio}: `description` nao diz QUANDO usar (esperado um \"Use quando/ao ...\")."
        );
    }

    #[DataProvider('agentesEComandos')]
    public function test_agente_e_comando_tem_descricao(string $caminho, string $tipo): void
    {
        $frontmatter = $this->frontmatter($caminho);
        $nome = basename($caminho);

        $this->assertArrayHasKey('description', $frontmatter, "{$tipo} {$nome}: falta `description` no frontmatter.");
        $this->assertNotEmpty($frontmatter['description'], "{$tipo} {$nome}: `description` vazia.");
    }

    /**
     * Todo hook registrado precisa existir e ser executavel — hook fantasma
     * falha em silencio, que e o pior modo de falhar.
     */
    public function test_hooks_registrados_existem_e_sao_executaveis(): void
    {
        $settings = json_decode((string) file_get_contents(self::raiz().'/.claude/settings.json'), true);
        $this->assertIsArray($settings, 'settings.json invalido.');

        $encontrados = 0;

        foreach ($settings['hooks'] ?? [] as $evento => $grupos) {
            foreach ($grupos as $grupo) {
                foreach ($grupo['hooks'] ?? [] as $hook) {
                    $comando = str_replace('${CLAUDE_PROJECT_DIR}', self::raiz(), $hook['command'] ?? '');

                    $this->assertFileExists($comando, "hook de {$evento} nao existe: {$comando}");
                    $this->assertTrue(is_executable($comando), "hook nao e executavel (chmod +x): {$comando}");
                    $encontrados++;
                }
            }
        }

        $this->assertGreaterThan(0, $encontrados, 'Nenhum hook registrado em settings.json.');
    }

    /**
     * Os hooks leem JSON do stdin. Quando dependiam so de `jq` — ausente no macOS —
     * todos saiam calados e a protecao do .env ficou desligada sem sinal nenhum.
     * A lib com fallback existe para isso; este teste impede a regressao.
     */
    public function test_hooks_nao_dependem_exclusivamente_de_jq(): void
    {
        $semLib = [];

        foreach (glob(self::raiz().'/.claude/hooks/*.sh') ?: [] as $hook) {
            if (basename($hook) === 'lib.sh') {
                continue;
            }

            $conteudo = (string) file_get_contents($hook);

            $usaJqDireto = preg_match('/\bjq\b/', $conteudo) === 1;
            $usaLib = str_contains($conteudo, 'lib.sh');

            if ($usaJqDireto && ! $usaLib) {
                $semLib[] = basename($hook);
            }
        }

        $this->assertSame(
            [],
            $semLib,
            'Hook(s) chamando `jq` direto, sem o fallback da lib: '.implode(', ', $semLib)
            .'. Em host sem jq eles falham em silencio — use `source lib.sh` + `hook_campo`.'
        );
    }
}
