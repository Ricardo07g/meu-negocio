<?php

declare(strict_types=1);

namespace Tests\Feature\Seguranca;

use App\Support\GuardaDeDebug;
use Tests\TestCase;

/**
 * Debug ligado em producao transforma qualquer excecao numa tela com stack trace,
 * codigo-fonte e queries. A trava existe porque o valor vem do painel de deploy,
 * onde nenhum teste alcanca — este teste prova o que o codigo faz com ele.
 */
class GuardaDeDebugTest extends TestCase
{
    private function emProducaoComDebug(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => true]);
    }

    public function test_em_producao_o_debug_e_desligado_mesmo_com_a_variavel_ligada(): void
    {
        $this->emProducaoComDebug();

        $this->assertTrue(GuardaDeDebug::aplicar($this->app));
        $this->assertFalse(config('app.debug'));
    }

    public function test_fora_de_producao_o_debug_continua_como_configurado(): void
    {
        $this->app['env'] = 'local';
        config(['app.debug' => true]);

        $this->assertFalse(GuardaDeDebug::aplicar($this->app));
        $this->assertTrue(config('app.debug'), 'Em dev o debug e a ferramenta de trabalho — nao pode sumir.');
    }

    public function test_em_producao_url_malformada_nao_mostra_a_tela_de_excecao(): void
    {
        $this->emProducaoComDebug();
        GuardaDeDebug::aplicar($this->app);

        // URL malformada (byte invalido em UTF-8): o caminho mais curto ate uma excecao.
        $resposta = $this->get('/%c0');

        $resposta->assertStatus(400);
        $resposta->assertDontSee('Exception trace');
        $resposta->assertDontSee('MalformedUrlException');
        $resposta->assertDontSee('vendor/laravel');
    }
}
