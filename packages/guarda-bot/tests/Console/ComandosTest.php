<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Tests\Console;

use Illuminate\Support\Facades\Http;
use Ricardo07g\GuardaBot\Tests\TestCase;

class ComandosTest extends TestCase
{
    private string $diretorio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->diretorio = sys_get_temp_dir().'/guarda-bot-'.bin2hex(random_bytes(4));
        mkdir($this->diretorio);
        file_put_contents($this->diretorio.'/.env', "APP_NAME=Teste\n# GUARDA_BOT_MODO=observar\nTURNSTILE_SITE_KEY=\n");
        $this->app->useEnvironmentPath($this->diretorio);
    }

    protected function tearDown(): void
    {
        @unlink($this->diretorio.'/.env');
        @rmdir($this->diretorio);

        parent::tearDown();
    }

    private function env(): string
    {
        return (string) file_get_contents($this->diretorio.'/.env');
    }

    private function secretAceita(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);
    }

    // ------------------------------------------------------------------
    // guarda-bot:instalar
    // ------------------------------------------------------------------

    public function test_instalar_fora_de_local_so_imprime_e_nao_mostra_a_secret(): void
    {
        $this->secretAceita();
        $antes = $this->env();

        $this->artisan('guarda-bot:instalar', [
            '--modo' => 'ativo',
            '--site-key' => 'site-123',
            '--secret-key' => 'secret-que-nao-pode-aparecer',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('GUARDA_BOT_MODO=ativo')
            ->expectsOutputToContain('TURNSTILE_SITE_KEY=site-123')
            ->doesntExpectOutputToContain('secret-que-nao-pode-aparecer')
            ->assertSuccessful();

        $this->assertSame($antes, $this->env());
    }

    public function test_instalar_recusa_secret_invalida(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-secret']])]);

        $this->artisan('guarda-bot:instalar', [
            '--modo' => 'ativo',
            '--site-key' => 'site-123',
            '--secret-key' => 'secret-errada',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('recusou')
            ->assertFailed();
    }

    public function test_instalar_em_local_com_consentimento_grava_no_env_substituindo_e_acrescentando(): void
    {
        $this->app['env'] = 'local';
        $this->secretAceita();

        $this->artisan('guarda-bot:instalar', [
            '--modo' => 'ativo',
            '--site-key' => 'site-123',
            '--secret-key' => 'secret-456',
            '--gravar-env' => true,
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('gravadas')
            ->assertSuccessful();

        $env = $this->env();
        $this->assertStringContainsString("GUARDA_BOT_MODO=ativo\n", $env);
        $this->assertStringNotContainsString('# GUARDA_BOT_MODO', $env);
        $this->assertStringContainsString("TURNSTILE_SITE_KEY=site-123\n", $env);
        $this->assertStringContainsString("TURNSTILE_SECRET_KEY=secret-456\n", $env);
        $this->assertSame(1, substr_count($env, 'TURNSTILE_SITE_KEY='));
    }

    public function test_instalar_em_local_sem_terminal_nem_consentimento_nao_grava(): void
    {
        $this->app['env'] = 'local';
        $antes = $this->env();

        $this->artisan('guarda-bot:instalar', ['--modo' => 'ativo', '--no-interaction' => true])
            ->expectsOutputToContain('GUARDA_BOT_MODO=ativo')
            ->assertSuccessful();

        $this->assertSame($antes, $this->env());
    }

    public function test_instalar_sem_interacao_e_sem_chaves_configura_so_o_modo(): void
    {
        Http::fake();

        $this->artisan('guarda-bot:instalar', ['--no-interaction' => true])
            ->expectsOutputToContain('GUARDA_BOT_MODO=observar')
            ->doesntExpectOutputToContain('TURNSTILE')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // guarda-bot:diagnosticar
    // ------------------------------------------------------------------

    public function test_diagnosticar_mostra_a_configuracao_efetiva(): void
    {
        $this->artisan('guarda-bot:diagnosticar')
            ->expectsOutputToContain('observar')
            ->expectsOutputToContain('AgenteDeUsuario')
            ->expectsOutputToContain('desligado (sem chaves)')
            ->assertSuccessful();
    }

    public function test_diagnosticar_simula_o_bloqueio_de_um_script(): void
    {
        $this->artisan('guarda-bot:diagnosticar', ['--ua' => 'python-requests/2.31.0'])
            ->expectsOutputToContain('automacao_declarada')
            ->expectsOutputToContain('bloquear')
            ->assertSuccessful();
    }

    public function test_diagnosticar_simula_a_passagem_de_um_navegador(): void
    {
        $this->artisan('guarda-bot:diagnosticar', ['--ua' => self::CHROME])
            ->expectsOutputToContain('permitir')
            ->assertSuccessful();
    }
}
