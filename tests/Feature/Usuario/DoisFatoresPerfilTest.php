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

        $this->post(route('perfil.dois-fatores.iniciar'))
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
        $this->post(route('perfil.dois-fatores.iniciar'));

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
        $this->post(route('perfil.dois-fatores.iniciar'));
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

        $this->post(route('perfil.dois-fatores.codigos'), ['senha_2fa' => 'errada'])->assertSessionHasErrors('senha_2fa');
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

        $this->delete(route('perfil.dois-fatores.desativar'), ['senha_2fa' => 'errada'])->assertSessionHasErrors('senha_2fa');

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
        $contexto['rede']->update(['exigir_2fa' => true]);

        $this->delete(route('perfil.dois-fatores.desativar'), ['senha_2fa' => 'password'])->assertSessionHas('erro');

        $this->assertTrue($contexto['usuario']->refresh()->temDoisFatoresAtivo());
    }
}
