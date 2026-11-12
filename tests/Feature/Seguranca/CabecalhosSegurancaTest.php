<?php

declare(strict_types=1);

namespace Tests\Feature\Seguranca;

use App\Enums\TipoConta;
use App\Modules\Conta\Models\Conta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\{MessageBag, ViewErrorBag};
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Cabecalhos de seguranca e CSP com nonce (ADR-0023).
 *
 * O eixo que mais importa e o "toda tag inline carrega o nonce": uma tag esquecida
 * nao quebra nenhum teste HTTP — so deixa a tela sem JS no navegador, em silencio.
 * O BladeLintTest cobre o fonte (inclusive ramos @if); aqui se prova o HTML
 * renderizado das telas com mais script, com o nonce do proprio cabecalho.
 */
class CabecalhosSegurancaTest extends TestCase
{
    use RefreshDatabase;

    private const FIXOS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        'X-Permitted-Cross-Domain-Policies' => 'none',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // O .env de dev liga o debug; o comportamento que vale e o de producao.
        config(['app.debug' => false, 'seguranca.csp.modo' => 'ativo']);
    }

    private function assertCabecalhosFixos(TestResponse $resposta): void
    {
        foreach (self::FIXOS as $nome => $valor) {
            $resposta->assertHeader($nome, $valor);
        }

        $this->assertStringContainsString('camera=()', (string) $resposta->headers->get('Permissions-Policy'));
    }

    private function nonceDoCabecalho(TestResponse $resposta): string
    {
        $csp = (string) $resposta->headers->get('Content-Security-Policy');

        $this->assertSame(1, preg_match("/'nonce-([A-Za-z0-9]+)'/", $csp, $m), 'CSP sem nonce: '.$csp);

        return $m[1];
    }

    /** Toda tag <script> inline do HTML carrega exatamente o nonce do cabecalho. */
    private function assertScriptsInlineComNonce(TestResponse $resposta, string $tela): void
    {
        $nonce = $this->nonceDoCabecalho($resposta);

        preg_match_all('/<script\b([^>]*)>/i', (string) $resposta->getContent(), $tags);

        $inline = array_filter(
            $tags[1],
            fn (string $atributos): bool => preg_match('/\bsrc\s*=/i', $atributos) !== 1
                && preg_match('/\btype\s*=\s*["\']application\/json["\']/i', $atributos) !== 1
        );

        $this->assertNotEmpty($inline, "{$tela}: esperava scripts inline (o layout tem varios).");

        foreach ($inline as $atributos) {
            $this->assertStringContainsString(
                'nonce="'.$nonce.'"',
                $atributos,
                "{$tela}: <script{$atributos}> sem o nonce da requisicao — o navegador nao executa."
            );
        }
    }

    // ------------------------------------------------------------------
    // Cabecalhos fixos e forma da politica
    // ------------------------------------------------------------------

    public function test_pagina_publica_sai_com_os_cabecalhos_e_a_csp_estrita(): void
    {
        $resposta = $this->get(route('login'))->assertOk();

        $this->assertCabecalhosFixos($resposta);

        $csp = (string) $resposta->headers->get('Content-Security-Policy');
        preg_match('/script-src ([^;]+)/', $csp, $scriptSrc);

        $this->assertStringContainsString("'nonce-", $scriptSrc[1] ?? '');
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc[1] ?? '', 'script-src com unsafe-inline anula o nonce.');
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString('https://challenges.cloudflare.com', $csp, 'Sem a origem do Turnstile o login quebra quando a chave esta ligada.');
    }

    public function test_o_nonce_muda_a_cada_requisicao(): void
    {
        $primeiro = $this->nonceDoCabecalho($this->get(route('login')));
        $segundo = $this->nonceDoCabecalho($this->get(route('login')));

        $this->assertNotSame($primeiro, $segundo);
        $this->assertGreaterThanOrEqual(32, strlen($primeiro));
    }

    public function test_hsts_so_e_enviado_em_https(): void
    {
        $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_hsts_nao_inclui_subdominios_que_nao_sao_nossos(): void
    {
        $hsts = (string) $this->get('https://localhost/login')->headers->get('Strict-Transport-Security');

        $this->assertStringNotContainsString('includeSubDomains', $hsts);
        $this->assertStringNotContainsString('preload', $hsts);
    }

    // ------------------------------------------------------------------
    // Telas autenticadas: todo script inline renderizado tem o nonce
    // ------------------------------------------------------------------

    public function test_telas_com_script_inline_renderizam_todos_com_o_nonce(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $banco = Conta::where('empresa_id', $contexto['empresa']->id)->where('tipo', TipoConta::Banco)->firstOrFail();

        $telas = [
            'dashboard' => route('dashboard'),
            'agenda' => route('agenda.index'),
            'venda nova' => route('vendas.create'),
            'caixa' => route('caixas.index'),
            'extrato' => route('contas.extrato', $banco),
            'carteira' => route('clientes.carteira'),
            'assinatura' => route('assinatura.index'),
            'nova despesa' => route('despesas.create'),
            'nova forma de pagamento' => route('formas-pagamento.create'),
            'novo perfil de acesso' => route('perfis-acesso.create'),
            'novo usuario' => route('usuarios.create'),
            'novo servico' => route('servicos.create'),
        ];

        foreach ($telas as $tela => $url) {
            $resposta = $this->get($url);

            $this->assertSame(200, $resposta->getStatusCode(), "{$tela} nao abriu ({$url}).");
            $this->assertScriptsInlineComNonce($resposta, $tela);
        }
    }

    public function test_scripts_de_flash_e_de_erro_de_validacao_tambem_tem_o_nonce(): void
    {
        $this->criarRedeAutenticada();

        // Os tres blocos condicionais do layout: so existem com flash/erro na sessao.
        $resposta = $this->withSession([
            'sucesso' => 'Salvo.',
            'erro' => 'Falhou.',
            'errors' => (new ViewErrorBag)->put('default', new MessageBag(['campo' => ['Obrigatorio.']])),
        ])->get(route('dashboard'))->assertOk();

        $this->assertScriptsInlineComNonce($resposta, 'dashboard com flash');
        $resposta->assertSee('Salvo.', false);
        $resposta->assertSee('Falhou.', false);
    }

    // ------------------------------------------------------------------
    // Onde a CSP nao entra
    // ------------------------------------------------------------------

    public function test_resposta_json_tem_os_cabecalhos_fixos_mas_nao_a_csp(): void
    {
        $this->criarRedeAutenticada();

        $resposta = $this->getJson(route('clientes.buscar', ['q' => 'Cliente']))->assertOk();

        $this->assertCabecalhosFixos($resposta);
        $resposta->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_respostas_de_erro_tambem_saem_protegidas(): void
    {
        $naoEncontrada = $this->get('/rota-que-nao-existe')->assertNotFound();
        $this->assertCabecalhosFixos($naoEncontrada);
        $naoEncontrada->assertHeader('Content-Security-Policy');

        // URL malformada para no ValidatePathEncoding, antes do roteamento.
        $malformada = $this->get('/%c0')->assertStatus(400);
        $this->assertCabecalhosFixos($malformada);
    }

    public function test_tela_de_debug_em_dev_fica_sem_csp_para_nao_perder_o_js_dela(): void
    {
        config(['app.debug' => true]);

        $resposta = $this->get('/%c0')->assertStatus(400);

        $resposta->assertHeaderMissing('Content-Security-Policy');
        $this->assertCabecalhosFixos($resposta);
    }

    // ------------------------------------------------------------------
    // Cookies
    // ------------------------------------------------------------------

    public function test_nao_emite_o_cookie_xsrf_token_legivel_por_script(): void
    {
        $resposta = $this->get(route('login'))->assertOk();

        $resposta->assertCookieMissing('XSRF-TOKEN');
        $resposta->assertCookie(config('session.cookie'));

        // O token continua disponivel para forms e fetch pelo caminho que o JS usa.
        $resposta->assertSee('name="csrf-token"', false);
    }

    // ------------------------------------------------------------------
    // Modos (rollout) e origens resolvidas em runtime
    // ------------------------------------------------------------------

    public function test_modo_relatorio_usa_o_cabecalho_report_only(): void
    {
        config(['seguranca.csp.modo' => 'relatorio']);

        $resposta = $this->get(route('login'));

        $resposta->assertHeaderMissing('Content-Security-Policy');
        $this->assertStringContainsString("'nonce-", (string) $resposta->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_modo_desligado_nao_envia_csp_nem_nonce_mas_mantem_os_demais(): void
    {
        config(['seguranca.csp.modo' => 'desligado']);
        $this->criarRedeAutenticada();

        $resposta = $this->get(route('dashboard'))->assertOk();

        $resposta->assertHeaderMissing('Content-Security-Policy');
        $resposta->assertHeaderMissing('Content-Security-Policy-Report-Only');
        $this->assertStringNotContainsString('nonce=', (string) $resposta->getContent());
        $this->assertCabecalhosFixos($resposta);
    }

    public function test_modo_desconhecido_cai_no_ativo(): void
    {
        config(['seguranca.csp.modo' => 'relatoro']);

        $this->get(route('login'))->assertHeader('Content-Security-Policy');
    }

    public function test_origem_publica_dos_arquivos_entra_no_img_src_sem_o_caminho(): void
    {
        config(['filesystems.disks.r2.url' => 'https://arquivos.exemplo.com/pasta/']);

        $csp = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');

        preg_match('/img-src ([^;]+)/', $csp, $imgSrc);
        $this->assertStringContainsString('https://arquivos.exemplo.com', $imgSrc[1] ?? '');
        $this->assertStringNotContainsString('/pasta', $imgSrc[1] ?? '');
    }
}
