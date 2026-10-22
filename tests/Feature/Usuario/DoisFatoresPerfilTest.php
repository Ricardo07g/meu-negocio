<?php

declare(strict_types=1);

namespace Tests\Feature\Usuario;

use App\Modules\Auth\Services\DoisFatoresService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\DoisFatores;
use Tests\TestCase;

/**
 * Aba Seguranca do Meu Perfil (ADR-0022): configurar, confirmar, gerar codigos
 * e desativar o proprio 2FA.
 */
class DoisFatoresPerfilTest extends TestCase
{
    use DoisFatores;
    use RefreshDatabase;

    public function test_aba_seguranca_oferece_configurar(): void
    {
        $this->criarRedeAutenticada();

        $this->get(route('perfil.index', ['aba' => 'seguranca']))
            ->assertOk()
            ->assertSee('Configurar verificação em duas etapas');
    }

    public function test_iniciar_grava_segredo_pendente_e_mostra_o_qr(): void
    {
        $usuario = $this->criarRedeAutenticada()['usuario'];

        $this->post(route('perfil.dois-fatores.iniciar'), ['senha_2fa' => 'password'])
            ->assertRedirect(route('perfil.index', ['aba' => 'seguranca']));

        $usuario->refresh();
        $this->assertNotNull($usuario->two_factor_secret);
        $this->assertFalse($usuario->temDoisFatoresAtivo(), 'Sem confirmar, o 2FA ainda nao vale.');

        $this->get(route('perfil.index', ['aba' => 'seguranca']))
            ->assertOk()
            ->assertSee('data-qr-dois-fatores', false)
            ->assertSee('<svg', false);
    }

    public function test_confirmar_com_codigo_certo_ativa_e_mostra_os_codigos_uma_vez(): void
    {
        $usuario = $this->criarRedeAutenticada()['usuario'];
        $this->post(route('perfil.dois-fatores.iniciar'), ['senha_2fa' => 'password']);

        $this->post(route('perfil.dois-fatores.confirmar'), ['codigo' => $this->codigoAtual($usuario)])
            ->assertRedirect(route('perfil.index', ['aba' => 'seguranca']))
            ->assertSessionHas('codigos_recuperacao', fn ($c) => count($c) === DoisFatoresService::QTD_CODIGOS_RECUPERACAO);

        $this->assertTrue($usuario->refresh()->temDoisFatoresAtivo());

        $this->get(route('perfil.index', ['aba' => 'seguranca']))->assertSee('data-codigos-recuperacao', false);
        $this->get(route('perfil.index', ['aba' => 'seguranca']))->assertDontSee('data-codigos-recuperacao', false);
    }

    public function test_confirmar_com_codigo_errado_nao_ativa(): void
    {
        $usuario = $this->criarRedeAutenticada()['usuario'];
        $this->post(route('perfil.dois-fatores.iniciar'), ['senha_2fa' => 'password']);
        $errado = $this->codigoAtual($usuario) === '000000' ? '111111' : '000000';

        $this->post(route('perfil.dois-fatores.confirmar'), ['codigo' => $errado])->assertSessionHas('erro');

        $this->assertFalse($usuario->refresh()->temDoisFatoresAtivo());
    }

    public function test_confirmar_sem_iniciar_e_recusado(): void
    {
        $usuario = $this->criarRedeAutenticada()['usuario'];

        $this->post(route('perfil.dois-fatores.confirmar'), ['codigo' => '123456'])->assertSessionHas('erro');

        $this->assertFalse($usuario->refresh()->temDoisFatoresAtivo());
    }

    public function test_segredo_e_codigos_ficam_criptografados_e_fora_da_serializacao(): void
    {
        $usuario = $this->criarRedeAutenticada()['usuario'];
        $codigos = $this->ativarDoisFatores($usuario);

        $bruto = DB::table('usuarios')->where('id', $usuario->id)->first();
        $this->assertNotSame($usuario->refresh()->two_factor_secret, $bruto->two_factor_secret);
        $this->assertStringNotContainsString($codigos[0], (string) $bruto->two_factor_recovery_codes);

        $array = $usuario->toArray();
        $this->assertArrayNotHasKey('two_factor_secret', $array);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $array);
    }

    public function test_gerar_novos_codigos_exige_a_senha(): void
    {
        $usuario = $this->criarRedeAutenticada()['usuario'];
        $this->ativarDoisFatores($usuario);
        $antes = $usuario->refresh()->two_factor_recovery_codes;

        $this->post(route('perfil.dois-fatores.codigos'), ['senha_2fa' => 'errada'])->assertSessionHasErrorsIn('codigos', 'senha_2fa');
        $this->assertSame($antes, $usuario->refresh()->two_factor_recovery_codes);
    }

    public function test_gerar_novos_codigos_invalida_os_anteriores(): void
    {
        $usuario = $this->criarRedeAutenticada()['usuario'];
        $antigos = $this->ativarDoisFatores($usuario);

        $this->post(route('perfil.dois-fatores.codigos'), ['senha_2fa' => 'password'])
            ->assertSessionHas('codigos_recuperacao');

        $this->assertFalse(app(DoisFatoresService::class)->usarCodigoRecuperacao($usuario->refresh(), $antigos[0]));
    }

    public function test_desativar_exige_a_senha(): void
    {
        $usuario = $this->criarRedeAutenticada()['usuario'];
        $this->ativarDoisFatores($usuario);

        $this->delete(route('perfil.dois-fatores.desativar'), ['senha_2fa' => 'errada'])->assertSessionHasErrorsIn('desativar', 'senha_2fa');

        $this->assertTrue($usuario->refresh()->temDoisFatoresAtivo());
    }

    public function test_desativar_com_a_senha_desliga(): void
    {
        $usuario = $this->criarRedeAutenticada()['usuario'];
        $this->ativarDoisFatores($usuario);

        $this->delete(route('perfil.dois-fatores.desativar'), ['senha_2fa' => 'password'])
            ->assertRedirect(route('perfil.index', ['aba' => 'seguranca']));

        $usuario->refresh();
        $this->assertFalse($usuario->temDoisFatoresAtivo());
        $this->assertNull($usuario->two_factor_secret);
    }

    public function test_desativar_e_recusado_quando_a_rede_exige(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->ativarDoisFatores($contexto['usuario']);
        $contexto['rede']->forceFill(['exigir_2fa' => true])->save();

        $this->delete(route('perfil.dois-fatores.desativar'), ['senha_2fa' => 'password'])->assertSessionHas('erro');

        $this->assertTrue($contexto['usuario']->refresh()->temDoisFatoresAtivo());
    }

    // ---------------------------------------------------------------------
    // Review (ADR-0022): defeitos encontrados na revisao, cobertos antes da correcao.
    // ---------------------------------------------------------------------

    /** Texto do invalid-feedback dentro do form marcado com data-form-2fa="$acao". */
    private function erroNoForm(string $html, string $acao): ?string
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $no = (new \DOMXPath($dom))
            ->query("//form[@data-form-2fa='{$acao}']//*[contains(concat(' ', @class, ' '), ' invalid-feedback ')]")
            ->item(0);

        return $no !== null ? trim($no->textContent) : null;
    }

    public function test_senha_errada_no_desativar_aparece_no_form_de_desativar(): void
    {
        $usuario = $this->criarRedeAutenticada()['usuario'];
        $this->ativarDoisFatores($usuario);

        $html = $this->followingRedirects()
            ->from(route('perfil.index', ['aba' => 'seguranca']))
            ->delete(route('perfil.dois-fatores.desativar'), ['senha_2fa' => 'errada'])
            ->assertOk()
            ->getContent();

        $this->assertSame('A senha informada está incorreta.', $this->erroNoForm($html, 'desativar'));
        $this->assertNull($this->erroNoForm($html, 'codigos'), 'O erro do desativar nao pode aparecer no form de gerar codigos.');
    }

    public function test_senha_errada_ao_gerar_codigos_aparece_no_form_de_codigos(): void
    {
        $usuario = $this->criarRedeAutenticada()['usuario'];
        $this->ativarDoisFatores($usuario);

        $html = $this->followingRedirects()
            ->from(route('perfil.index', ['aba' => 'seguranca']))
            ->post(route('perfil.dois-fatores.codigos'), ['senha_2fa' => 'errada'])
            ->assertOk()
            ->getContent();

        $this->assertSame('A senha informada está incorreta.', $this->erroNoForm($html, 'codigos'));
        $this->assertNull($this->erroNoForm($html, 'desativar'));
    }

    /** tratarErro devolvia TODO o input com withInput(): a senha ia parar na sessao. */
    public function test_senha_nao_fica_guardada_na_sessao_quando_a_acao_e_recusada(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->ativarDoisFatores($contexto['usuario']);
        $contexto['rede']->forceFill(['exigir_2fa' => true])->save();

        $this->delete(route('perfil.dois-fatores.desativar'), ['senha_2fa' => 'password'])
            ->assertSessionHas('erro')
            ->assertSessionMissing('_old_input.senha_2fa');
    }

    /** Sessao esquecida aberta nao pode amarrar a conta ao celular de outra pessoa. */
    public function test_iniciar_exige_a_senha(): void
    {
        $usuario = $this->criarRedeAutenticada()['usuario'];

        $this->post(route('perfil.dois-fatores.iniciar'))->assertSessionHasErrorsIn('iniciar', 'senha_2fa');
        $this->post(route('perfil.dois-fatores.iniciar'), ['senha_2fa' => 'errada'])->assertSessionHasErrorsIn('iniciar', 'senha_2fa');

        $this->assertNull($usuario->refresh()->two_factor_secret);
    }

    /** Senha conferida por formulario precisa de limite, senao vira oraculo de forca bruta. */
    public function test_acoes_com_senha_respeitam_limite_por_minuto(): void
    {
        $usuario = $this->criarRedeAutenticada()['usuario'];
        $this->ativarDoisFatores($usuario);

        for ($i = 0; $i < 5; $i++) {
            $this->delete(route('perfil.dois-fatores.desativar'), ['senha_2fa' => 'errada']);
        }

        $this->delete(route('perfil.dois-fatores.desativar'), ['senha_2fa' => 'password'])->assertStatus(429);
        $this->assertTrue($usuario->refresh()->temDoisFatoresAtivo());
    }

    /**
     * O `throttle:N,1` numerico usa como chave so o usuario: configurar, confirmar
     * e gerar codigos dividiam o mesmo balde, e um erro de digitacao no caminho
     * normal de configuracao terminava em 429. Cada acao precisa do proprio limite.
     */
    public function test_configurar_com_um_erro_de_digitacao_nao_esbarra_no_limite(): void
    {
        $usuario = $this->criarRedeAutenticada()['usuario'];

        $this->post(route('perfil.dois-fatores.iniciar'), ['senha_2fa' => 'password'])->assertRedirect();
        $this->post(route('perfil.dois-fatores.confirmar'), ['codigo' => '000001'])->assertRedirect();
        $this->post(route('perfil.dois-fatores.confirmar'), ['codigo' => $this->codigoAtual($usuario)])->assertRedirect();
        $usuario->refresh()->forceFill(['two_factor_ultimo_passo' => null])->save();

        $this->post(route('perfil.dois-fatores.codigos'), ['senha_2fa' => 'errada'])->assertRedirect();
        $this->post(route('perfil.dois-fatores.codigos'), ['senha_2fa' => 'errada'])->assertRedirect();
        $this->post(route('perfil.dois-fatores.codigos'), ['senha_2fa' => 'password'])
            ->assertRedirect(route('perfil.index', ['aba' => 'seguranca']))
            ->assertSessionHas('codigos_recuperacao');
    }
}
