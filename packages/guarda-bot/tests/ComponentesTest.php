<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Tests;

use Illuminate\Support\Facades\Blade;
use Ricardo07g\GuardaBot\Detectores\Armadilha;
use Ricardo07g\GuardaBot\Sinal;

class ComponentesTest extends TestCase
{
    public function test_armadilha_renderiza_honeypot_fora_da_tela_e_carimbo_valido(): void
    {
        $html = Blade::render('<x-guarda-bot::armadilha />');

        $this->assertStringContainsString('name="website"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertMatchesRegularExpression('/name="guarda_bot_t"\s+value="([^"]+)"/', $html);

        // O carimbo renderizado e aceito pelo detector (depois do tempo minimo).
        preg_match('/name="guarda_bot_t"\s+value="([^"]+)"/', $html, $m);
        $this->travel(5)->seconds();
        $sinais = app(Armadilha::class)->avaliar($this->requisicao(
            $this->cabecalhosDeNavegador(),
            ['website' => '', 'guarda_bot_t' => html_entity_decode($m[1])],
        ));

        $this->assertSame([], array_map(fn (Sinal $s): string => $s->motivo, $sinais));
    }

    public function test_armadilha_some_com_o_modo_desligado(): void
    {
        config(['guarda-bot.modo' => 'desligado']);

        $this->assertSame('', trim(Blade::render('<x-guarda-bot::armadilha />')));
    }

    public function test_turnstile_so_renderiza_com_as_chaves(): void
    {
        $this->assertStringNotContainsString('cf-turnstile', Blade::render('<x-guarda-bot::turnstile />'));

        config([
            'guarda-bot.turnstile.site_key' => 'chave-publica-de-teste',
            'guarda-bot.turnstile.secret_key' => 'chave-secreta-de-teste',
        ]);

        $html = Blade::render('<x-guarda-bot::turnstile />');

        $this->assertStringContainsString('data-sitekey="chave-publica-de-teste"', $html);
        $this->assertStringContainsString('challenges.cloudflare.com/turnstile/v0/api.js', $html);
    }

    public function test_protecao_junta_armadilha_e_turnstile(): void
    {
        config([
            'guarda-bot.turnstile.site_key' => 'chave-publica-de-teste',
            'guarda-bot.turnstile.secret_key' => 'chave-secreta-de-teste',
        ]);

        $html = Blade::render('<x-guarda-bot::protecao />');

        $this->assertStringContainsString('name="website"', $html);
        $this->assertStringContainsString('cf-turnstile', $html);
    }
}
