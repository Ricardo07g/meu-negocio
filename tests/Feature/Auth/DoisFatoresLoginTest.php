<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Services\DoisFatoresService;
use App\Modules\Usuario\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\DoisFatores;
use Tests\TestCase;

/**
 * Login em duas etapas (ADR-0022): com 2FA ativo, a senha certa so leva ao
 * desafio — a sessao so e autenticada depois do codigo do app (ou de um codigo
 * de recuperacao).
 */
class DoisFatoresLoginTest extends TestCase
{
    use DoisFatores;
    use RefreshDatabase;

    private function entrarComSenha(Usuario $usuario): TestResponse
    {
        return $this->post(route('login'), ['email' => $usuario->email, 'password' => 'password']);
    }

    public function test_sem_2fa_o_login_segue_direto(): void
    {
        $usuario = $this->criarRede()['usuario'];

        $this->entrarComSenha($usuario)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_senha_certa_com_2fa_nao_autentica_antes_do_desafio(): void
    {
        $usuario = $this->criarRede()['usuario'];
        $this->ativarDoisFatores($usuario);

        $this->entrarComSenha($usuario)->assertRedirect(route('login.dois-fatores'));

        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('login.dois-fatores'))->assertOk()->assertSee('Verificação em duas etapas');
    }

    public function test_senha_errada_nem_chega_ao_desafio(): void
    {
        $usuario = $this->criarRede()['usuario'];
        $this->ativarDoisFatores($usuario);

        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'errada'])
            ->assertSessionHasErrors('email');

        $this->get(route('login.dois-fatores'))->assertRedirect(route('login'));
    }

    public function test_codigo_certo_completa_o_login(): void
    {
        $usuario = $this->criarRede()['usuario'];
        $this->ativarDoisFatores($usuario);
        $this->entrarComSenha($usuario);

        $this->post(route('login.dois-fatores'), ['codigo' => $this->codigoAtual($usuario)])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_codigo_errado_e_recusado(): void
    {
        $usuario = $this->criarRede()['usuario'];
        $this->ativarDoisFatores($usuario);
        $this->entrarComSenha($usuario);

        $errado = $this->codigoAtual($usuario) === '000000' ? '111111' : '000000';

        $this->post(route('login.dois-fatores'), ['codigo' => $errado])->assertSessionHasErrors('codigo');
        $this->assertGuest();
    }

    /** Codigo interceptado (phishing, ombro) nao serve para uma segunda sessao. */
    public function test_mesmo_codigo_nao_entra_duas_vezes(): void
    {
        $usuario = $this->criarRede()['usuario'];
        $this->ativarDoisFatores($usuario);
        $codigo = $this->codigoAtual($usuario);

        $this->entrarComSenha($usuario);
        $this->post(route('login.dois-fatores'), ['codigo' => $codigo])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));

        $this->entrarComSenha($usuario);
        $this->post(route('login.dois-fatores'), ['codigo' => $codigo])->assertSessionHasErrors('codigo');
        $this->assertGuest();
    }

    public function test_codigo_de_recuperacao_entra_uma_vez_so(): void
    {
        $usuario = $this->criarRede()['usuario'];
        $codigos = $this->ativarDoisFatores($usuario);

        $this->entrarComSenha($usuario);
        // Digitado em minusculas e sem hifen: a normalizacao aceita.
        $this->post(route('login.dois-fatores'), ['codigo_recuperacao' => strtolower(str_replace('-', '', $codigos[0]))])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($usuario);
        $this->assertCount(DoisFatoresService::QTD_CODIGOS_RECUPERACAO - 1, $usuario->refresh()->two_factor_recovery_codes);

        $this->post(route('logout'));
        $this->entrarComSenha($usuario);
        $this->post(route('login.dois-fatores'), ['codigo_recuperacao' => $codigos[0]])
            ->assertSessionHasErrors('codigo_recuperacao');
        $this->assertGuest();
    }

    public function test_pendencia_expira_em_cinco_minutos(): void
    {
        $usuario = $this->criarRede()['usuario'];
        $this->ativarDoisFatores($usuario);
        $this->entrarComSenha($usuario);

        $this->travel(6)->minutes();

        $this->post(route('login.dois-fatores'), ['codigo' => $this->codigoAtual($usuario)])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_desafio_sem_pendencia_volta_ao_login(): void
    {
        $this->get(route('login.dois-fatores'))->assertRedirect(route('login'));
        $this->post(route('login.dois-fatores'), ['codigo' => '123456'])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_usuario_desativado_durante_o_desafio_e_recusado(): void
    {
        $usuario = $this->criarRede()['usuario'];
        $this->ativarDoisFatores($usuario);
        $this->entrarComSenha($usuario);

        $usuario->update(['ativo' => false]);

        $this->post(route('login.dois-fatores'), ['codigo' => $this->codigoAtual($usuario)])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * Login e desafio dividiam o balde de 5/min por IP do throttle: senha + 4
     * codigos errados ja davam 429 cru, antes da mensagem do teto da pendencia.
     */
    public function test_quatro_erros_no_mesmo_ip_ainda_permitem_acertar(): void
    {
        $usuario = $this->criarRede()['usuario'];
        $this->ativarDoisFatores($usuario);
        $this->entrarComSenha($usuario);

        for ($i = 0; $i < 4; $i++) {
            $this->errarCodigo($usuario, '127.0.0.1');
        }

        $this->post(route('login.dois-fatores'), ['codigo' => $this->codigoAtual($usuario)])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($usuario);
    }

    /** Teto da pendencia estourado no mesmo IP: mensagem amigavel, nao 429. */
    public function test_cinco_erros_no_mesmo_ip_voltam_ao_login_com_aviso(): void
    {
        $usuario = $this->criarRede()['usuario'];
        $this->ativarDoisFatores($usuario);
        $this->entrarComSenha($usuario);

        for ($i = 0; $i < 4; $i++) {
            $this->errarCodigo($usuario, '127.0.0.1');
        }

        $errado = $this->codigoAtual($usuario) === '000000' ? '111111' : '000000';
        $this->post(route('login.dois-fatores'), ['codigo' => $errado])
            ->assertRedirect(route('login'))
            ->assertSessionHas('erro', 'Muitas tentativas inválidas. Entre com email e senha novamente.');
        $this->assertGuest();
    }

    /** O desafio ainda tem limite por IP (balde proprio, 10/min) contra rajada. */
    public function test_desafio_respeita_o_limite_por_minuto(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('login.dois-fatores'), ['codigo' => '000000']);
        }

        $this->post(route('login.dois-fatores'), ['codigo' => '000000'])->assertStatus(429);
    }

    private function errarCodigo(Usuario $usuario, string $ip): void
    {
        $errado = $this->codigoAtual($usuario) === '000000' ? '111111' : '000000';
        $this->withServerVariables(['REMOTE_ADDR' => $ip])->post(route('login.dois-fatores'), ['codigo' => $errado]);
    }

    /**
     * O throttle e por IP: trocar de IP renovava as tentativas. O limite precisa
     * ser da pendencia — estourou, volta ao login (e ao Turnstile).
     */
    public function test_pendencia_cai_apos_cinco_erros_mesmo_trocando_de_ip(): void
    {
        $usuario = $this->criarRede()['usuario'];
        $this->ativarDoisFatores($usuario);
        $this->entrarComSenha($usuario);

        for ($i = 1; $i <= 5; $i++) {
            $this->errarCodigo($usuario, "10.0.0.{$i}");
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->post(route('login.dois-fatores'), ['codigo' => $this->codigoAtual($usuario)])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_quatro_erros_ainda_permitem_acertar(): void
    {
        $usuario = $this->criarRede()['usuario'];
        $this->ativarDoisFatores($usuario);
        $this->entrarComSenha($usuario);

        for ($i = 1; $i <= 4; $i++) {
            $this->errarCodigo($usuario, "10.0.1.{$i}");
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.99'])
            ->post(route('login.dois-fatores'), ['codigo' => $this->codigoAtual($usuario)])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($usuario);
    }

    /** Pendencia de outra conta nao sobrevive a um login completo no mesmo navegador. */
    public function test_login_sem_2fa_descarta_pendencia_anterior(): void
    {
        $comDoisFatores = $this->criarRede()['usuario'];
        $this->ativarDoisFatores($comDoisFatores);
        $semDoisFatores = $this->criarRede()['usuario'];

        $this->entrarComSenha($comDoisFatores)->assertSessionHas('login.dois_fatores');
        $this->entrarComSenha($semDoisFatores)->assertSessionMissing('login.dois_fatores');
    }
}
