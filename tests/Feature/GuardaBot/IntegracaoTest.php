<?php

declare(strict_types=1);

namespace Tests\Feature\GuardaBot;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as Rota;
use Illuminate\Support\Facades\Route;
use Ricardo07g\GuardaBot\Detectores\Armadilha;
use Ricardo07g\GuardaBot\Http\Middleware\ProtegerContraBots;
use Tests\TestCase;

/**
 * O pacote GuardaBot ligado ao Meu Negocio (ADR-0024).
 *
 * O comportamento dos detectores e testado no proprio pacote
 * (packages/guarda-bot/tests). Aqui o que importa e a INTEGRACAO: toda porta
 * publica passa por ele, as telas carregam a armadilha e um navegador real
 * continua entrando.
 */
class IntegracaoTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36';

    /**
     * @return array<string, string>
     */
    private function navegador(): array
    {
        return [
            'User-Agent' => self::CHROME,
            'Accept-Language' => 'pt-BR,pt;q=0.9',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-Mode' => 'navigate',
        ];
    }

    /**
     * Campos da armadilha de um formulario aberto ha 10 segundos.
     *
     * @return array<string, string>
     */
    private function armadilha(): array
    {
        return [
            'website' => '',
            'guarda_bot_t' => app(Armadilha::class)->carimbo(now()->subSeconds(10)->getTimestampMs()),
        ];
    }

    public function test_todo_post_publico_passa_pelo_guarda_bot(): void
    {
        $postsPublicos = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (Rota $rota): bool => in_array('POST', $rota->methods(), true)
                && in_array('guest', $rota->gatherMiddleware(), true));

        // Se este numero mudou, uma rota publica nasceu (ou sumiu): decida se ela
        // precisa do GuardaBot antes de ajustar.
        $this->assertSame(
            ['esqueci-senha', 'login', 'login/dois-fatores', 'redefinir-senha', 'registrar'],
            $postsPublicos->map(fn (Rota $rota): string => $rota->uri())->sort()->values()->all(),
        );

        foreach ($postsPublicos as $rota) {
            $this->assertContains('guarda-bot', $rota->gatherMiddleware(), "POST {$rota->uri()} sem o GuardaBot.");
        }
    }

    public function test_telas_publicas_renderizam_a_armadilha(): void
    {
        config(['guarda-bot.modo' => 'observar']);

        foreach ([route('login'), route('registrar'), route('senha.solicitar'), route('senha.redefinir.form', 'token')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('name="website"', escape: false)
                ->assertSee('name="guarda_bot_t"', escape: false);
        }
    }

    public function test_desligado_as_telas_ficam_como_antes(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('name="website"', escape: false);
    }

    public function test_modo_ativo_barra_headless_no_login_mesmo_com_senha_certa(): void
    {
        config(['guarda-bot.modo' => 'ativo']);
        $contexto = $this->criarRede();

        $this->from(route('login'))
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/142.0.0.0 Safari/537.36'])
            ->post(route('login'), ['email' => $contexto['usuario']->email, 'password' => 'password'] + $this->armadilha())
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(ProtegerContraBots::CHAVE_ERRO);

        $this->assertGuest();
    }

    public function test_modo_ativo_barra_robo_que_preenche_o_honeypot_no_cadastro(): void
    {
        config(['guarda-bot.modo' => 'ativo']);

        $this->from(route('registrar'))
            ->withHeaders($this->navegador())
            ->post(route('registrar'), ['website' => 'https://spam.example'] + $this->armadilha())
            ->assertRedirect(route('registrar'))
            ->assertSessionHasErrors(ProtegerContraBots::CHAVE_ERRO);
    }

    public function test_modo_ativo_deixa_navegador_real_entrar(): void
    {
        config(['guarda-bot.modo' => 'ativo']);
        $contexto = $this->criarRede();

        $this->withHeaders($this->navegador())
            ->post(route('login'), ['email' => $contexto['usuario']->email, 'password' => 'password'] + $this->armadilha())
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($contexto['usuario']);
    }

    public function test_redefinir_senha_tem_limite_de_tentativas(): void
    {
        $dados = ['token' => 'token-invalido', 'email' => 'ninguem@exemplo.com', 'password' => 'NovaSenha123', 'password_confirmation' => 'NovaSenha123'];

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('senha.redefinir'), $dados)->assertRedirect();
        }

        $this->post(route('senha.redefinir'), $dados)->assertTooManyRequests();
    }
}
