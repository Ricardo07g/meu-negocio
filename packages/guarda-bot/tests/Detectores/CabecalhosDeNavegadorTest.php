<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Tests\Detectores;

use Ricardo07g\GuardaBot\Detectores\CabecalhosDeNavegador;
use Ricardo07g\GuardaBot\Sinal;
use Ricardo07g\GuardaBot\Tests\TestCase;

class CabecalhosDeNavegadorTest extends TestCase
{
    /**
     * @param  array<string, string>  $cabecalhos
     * @return list<string>
     */
    private function motivos(array $cabecalhos, bool $https = true): array
    {
        return array_map(
            fn (Sinal $s): string => $s->motivo,
            (new CabecalhosDeNavegador)->avaliar($this->requisicao($cabecalhos, https: $https)),
        );
    }

    /**
     * @param  array<string, string>  $cabecalhos
     * @return array<string, string>
     */
    private function sem(array $cabecalhos, string ...$nomes): array
    {
        return array_diff_key($cabecalhos, array_flip($nomes));
    }

    public function test_navegador_completo_nao_gera_sinal(): void
    {
        $this->assertSame([], $this->motivos($this->cabecalhosDeNavegador()));
    }

    public function test_cliente_que_nao_finge_ser_navegador_fica_para_outro_detector(): void
    {
        $this->assertSame([], $this->motivos(['User-Agent' => 'curl/8.5.0']));
    }

    public function test_navegador_sem_accept_language_e_sinalizado(): void
    {
        $this->assertSame(
            ['sem_accept_language'],
            $this->motivos($this->sem($this->cabecalhosDeNavegador(), 'Accept-Language')),
        );
    }

    public function test_navegador_sem_sec_fetch_em_https_e_sinalizado(): void
    {
        $this->assertSame(
            ['sem_sec_fetch'],
            $this->motivos($this->sem($this->cabecalhosDeNavegador(), 'Sec-Fetch-Site', 'Sec-Fetch-Mode')),
        );
    }

    public function test_falta_de_sec_fetch_em_http_nao_e_sinal(): void
    {
        // Navegador so envia Sec-Fetch-* para origem segura.
        $this->assertSame(
            [],
            $this->motivos($this->sem($this->cabecalhosDeNavegador(), 'Sec-Fetch-Site', 'Sec-Fetch-Mode'), https: false),
        );
    }

    public function test_client_hint_de_headless_e_sinalizado(): void
    {
        $this->assertSame(
            ['client_hint_headless'],
            $this->motivos($this->cabecalhosDeNavegador() + ['Sec-CH-UA' => '"Chromium";v="142", "HeadlessChrome";v="142"']),
        );
    }

    public function test_safari_sem_client_hints_nao_e_sinalizado(): void
    {
        $safari = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15';

        $this->assertSame([], $this->motivos($this->cabecalhosDeNavegador($safari)));
    }
}
