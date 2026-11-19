<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Tests\Detectores;

use Ricardo07g\GuardaBot\Detectores\Armadilha;
use Ricardo07g\GuardaBot\Sinal;
use Ricardo07g\GuardaBot\Tests\TestCase;

class ArmadilhaTest extends TestCase
{
    /**
     * @param  array<string, string>  $dados
     * @return list<string>
     */
    private function motivos(array $dados, string $metodo = 'POST'): array
    {
        return array_map(
            fn (Sinal $s): string => $s->motivo,
            app(Armadilha::class)->avaliar($this->requisicao($this->cabecalhosDeNavegador(), $dados, $metodo)),
        );
    }

    public function test_formulario_preenchido_por_humano_nao_gera_sinal(): void
    {
        $this->assertSame([], $this->motivos($this->armadilha(segundosAtras: 10)));
    }

    public function test_get_nao_e_avaliado(): void
    {
        $this->assertSame([], $this->motivos([], 'GET'));
    }

    public function test_post_sem_os_campos_da_armadilha_e_sinalizado(): void
    {
        $this->assertSame(['armadilha_ausente'], $this->motivos(['email' => 'a@b.c']));
    }

    public function test_honeypot_preenchido_e_sinalizado(): void
    {
        $this->assertSame(
            ['armadilha_preenchida'],
            $this->motivos($this->armadilha(honeypot: 'https://spam.example')),
        );
    }

    public function test_carimbo_forjado_e_sinalizado(): void
    {
        $dados = $this->armadilha();
        $dados[(string) config('guarda-bot.armadilha.campo_tempo')] = 'nao-e-um-carimbo';

        $this->assertSame(['armadilha_invalida'], $this->motivos($dados));
    }

    public function test_envio_mais_rapido_que_o_minimo_e_sinalizado(): void
    {
        $this->assertSame(['envio_rapido'], $this->motivos($this->armadilha(segundosAtras: 0)));
    }
}
