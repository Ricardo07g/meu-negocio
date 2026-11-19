<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Tests;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Ricardo07g\GuardaBot\{AvaliadorDeRisco, Sinal};
use Ricardo07g\GuardaBot\Contratos\Detector;
use Ricardo07g\GuardaBot\Veredito;
use stdClass;

class AvaliadorDeRiscoTest extends TestCase
{
    private function avaliar(Request $request): Veredito
    {
        return app(AvaliadorDeRisco::class)->avaliar($request);
    }

    public function test_navegador_real_com_formulario_legitimo_tem_score_zero(): void
    {
        $veredito = $this->avaliar($this->requisicao($this->cabecalhosDeNavegador(), $this->armadilha()));

        $this->assertSame(0, $veredito->score);
        $this->assertTrue($veredito->limpo());
        $this->assertFalse($veredito->deveBloquear());
    }

    public function test_sinal_isolado_abaixo_do_limiar_nao_bloqueia(): void
    {
        // Gerenciador de senha: formulario enviado rapido, de resto tudo certo.
        $veredito = $this->avaliar($this->requisicao($this->cabecalhosDeNavegador(), $this->armadilha(segundosAtras: 0)));

        $this->assertSame(['envio_rapido' => 40], $veredito->motivos);
        $this->assertFalse($veredito->deveBloquear());
    }

    public function test_soma_de_sinais_fracos_cruza_o_limiar(): void
    {
        // UA de Chrome copiado por script: sem Accept-Language e postando na hora.
        $cabecalhos = $this->cabecalhosDeNavegador();
        unset($cabecalhos['Accept-Language']);

        $veredito = $this->avaliar($this->requisicao($cabecalhos, $this->armadilha(segundosAtras: 0)));

        $this->assertSame(70, $veredito->score);
        $this->assertTrue($veredito->deveBloquear());
    }

    public function test_score_tem_teto_de_100(): void
    {
        $veredito = $this->avaliar($this->requisicao(
            ['User-Agent' => 'curl/8.5.0'],
            $this->armadilha(segundosAtras: 0, honeypot: 'spam'),
        ));

        $this->assertSame(100, $veredito->score);
    }

    public function test_peso_da_config_prevalece_sobre_o_sugerido_pelo_detector(): void
    {
        config([
            'guarda-bot.detectores' => [DetectorDeTeste::class],
            'guarda-bot.pesos.sinal_de_teste' => 5,
        ]);

        $this->assertSame(['sinal_de_teste' => 5], $this->avaliar($this->requisicao([]))->motivos);
    }

    public function test_detector_proprio_sem_peso_na_config_usa_o_peso_sugerido(): void
    {
        config(['guarda-bot.detectores' => [DetectorDeTeste::class]]);

        $this->assertSame(['sinal_de_teste' => 55], $this->avaliar($this->requisicao([]))->motivos);
    }

    public function test_classe_que_nao_e_detector_e_recusada(): void
    {
        config(['guarda-bot.detectores' => [stdClass::class]]);

        $this->expectException(InvalidArgumentException::class);

        $this->avaliar($this->requisicao([]));
    }
}

class DetectorDeTeste implements Detector
{
    public function avaliar(Request $request): array
    {
        return [new Sinal('sinal_de_teste', 55)];
    }
}
