<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Ricardo07g\GuardaBot\Http\Middleware\ProtegerContraBots;

class ProtegerContraBotsTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->post('/formulario', fn (Request $request) => response()->json($request->all()))
            ->middleware(['web', 'guarda-bot'])
            ->name('formulario');
    }

    private function curl(): array
    {
        return ['User-Agent' => 'curl/8.5.0'];
    }

    private function espiarLog(): MockInterface
    {
        $log = Log::spy();
        $log->shouldReceive('channel')->andReturnSelf();

        return $log;
    }

    public function test_modo_desligado_nao_avalia_nem_registra(): void
    {
        config(['guarda-bot.modo' => 'desligado']);
        $log = $this->espiarLog();

        $this->withHeaders($this->curl())->post('/formulario', ['email' => 'a@b.c'])->assertOk();

        $log->shouldNotHaveReceived('warning');
    }

    public function test_modo_observar_registra_o_suspeito_e_deixa_passar(): void
    {
        $log = $this->espiarLog();

        $this->withHeaders($this->curl())->post('/formulario', ['email' => 'a@b.c'])->assertOk();

        $log->shouldHaveReceived('warning')->once()->with('guarda-bot: requisicao suspeita', Mockery::on(
            fn (array $contexto): bool => $contexto['acao'] === 'permitida'
                && $contexto['modo'] === 'observar'
                && $contexto['score'] === 100
                && array_key_exists('automacao_declarada', $contexto['motivos'])
                && $contexto['rota'] === 'formulario',
        ));
    }

    public function test_modo_ativo_bloqueia_o_robo_sem_devolver_a_senha(): void
    {
        config(['guarda-bot.modo' => 'ativo']);
        $this->espiarLog();

        $this->from('/entrar')
            ->withHeaders($this->curl())
            ->post('/formulario', ['email' => 'a@b.c', 'password' => 'segredo'])
            ->assertRedirect('/entrar')
            ->assertSessionHasErrors(ProtegerContraBots::CHAVE_ERRO)
            ->assertSessionHasInput('email', 'a@b.c')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_modo_ativo_registra_o_bloqueio(): void
    {
        config(['guarda-bot.modo' => 'ativo']);
        $log = $this->espiarLog();

        $this->from('/entrar')->withHeaders($this->curl())->post('/formulario');

        $log->shouldHaveReceived('warning')->once()->with('guarda-bot: requisicao suspeita', Mockery::on(
            fn (array $contexto): bool => $contexto['acao'] === 'bloqueada',
        ));
    }

    public function test_modo_ativo_responde_403_para_quem_espera_json(): void
    {
        config(['guarda-bot.modo' => 'ativo']);
        $this->espiarLog();

        $this->withHeaders($this->curl())->postJson('/formulario')->assertForbidden();
    }

    public function test_modo_ativo_deixa_passar_navegador_real_e_tira_os_campos_da_armadilha(): void
    {
        config(['guarda-bot.modo' => 'ativo']);
        $log = $this->espiarLog();

        $this->withHeaders($this->cabecalhosDeNavegador())
            ->post('/formulario', ['email' => 'a@b.c'] + $this->armadilha())
            ->assertOk()
            ->assertExactJson(['email' => 'a@b.c']);

        $log->shouldNotHaveReceived('warning');
    }

    public function test_modo_ativo_nao_bloqueia_sinal_isolado_abaixo_do_limiar(): void
    {
        config(['guarda-bot.modo' => 'ativo']);
        $this->espiarLog();

        $this->withHeaders($this->cabecalhosDeNavegador())
            ->post('/formulario', $this->armadilha(segundosAtras: 0))
            ->assertOk();
    }

    public function test_modo_desconhecido_cai_em_observar_e_nao_bloqueia(): void
    {
        config(['guarda-bot.modo' => 'ativado']);
        $this->espiarLog();

        $this->withHeaders($this->curl())->post('/formulario')->assertOk();
    }

    public function test_log_usa_o_canal_configurado(): void
    {
        config(['guarda-bot.log_canal' => 'seguranca']);
        $log = Log::spy();
        $log->shouldReceive('channel')->with('seguranca')->once()->andReturnSelf();

        $this->withHeaders($this->curl())->post('/formulario')->assertOk();

        $log->shouldHaveReceived('warning')->once();
    }
}
