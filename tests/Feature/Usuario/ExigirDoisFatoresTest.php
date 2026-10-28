<?php

declare(strict_types=1);

namespace Tests\Feature\Usuario;

use App\Modules\Tenant\Models\Rede;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\DoisFatores;
use Tests\TestCase;

/**
 * Acoes do Admin sobre o 2FA (ADR-0022): exigir na rede e redefinir o de um
 * usuario. Cobre a alcada (403), o isolamento entre redes e o bloqueio de quem
 * ainda nao configurou quando a rede exige.
 */
class ExigirDoisFatoresTest extends TestCase
{
    use DoisFatores;
    use RefreshDatabase;

    public function test_admin_passa_a_exigir_e_volta_a_opcional(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->ativarDoisFatores($contexto['usuario']);

        $this->patch(route('rede.exigir-2fa'), ['exigir' => 1])->assertRedirect(route('usuarios.index'));
        $this->assertTrue($contexto['rede']->refresh()->exigir_2fa);

        $this->patch(route('rede.exigir-2fa'), ['exigir' => 0])->assertRedirect(route('usuarios.index'));
        $this->assertFalse($contexto['rede']->refresh()->exigir_2fa);
    }

    public function test_exigencia_vale_so_para_a_rede_do_admin(): void
    {
        $outra = $this->criarRede('outra')['rede'];
        $this->criarRedeAutenticada();

        $this->patch(route('rede.exigir-2fa'), ['exigir' => 1]);

        $this->assertFalse($outra->refresh()->exigir_2fa);
    }

    public function test_nao_admin_nao_altera_a_exigencia(): void
    {
        $contexto = $this->criarRede();
        $gerente = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Gerente');
        $gerente->givePermissionTo(['usuario.ver', 'usuario.editar']);
        $this->actingAs($gerente);

        $this->patch(route('rede.exigir-2fa'), ['exigir' => 1])->assertForbidden();
        $this->assertFalse($contexto['rede']->refresh()->exigir_2fa);
    }

    public function test_card_da_exigencia_aparece_so_para_o_admin(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->get(route('usuarios.index'))->assertOk()->assertSee('Exigir de todos');

        $gerente = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Gerente');
        $gerente->givePermissionTo(['usuario.ver']);
        $this->actingAs($gerente)->get(route('usuarios.index'))->assertOk()->assertDontSee('Exigir de todos');
    }

    public function test_com_exigencia_quem_nao_configurou_e_levado_ao_perfil(): void
    {
        $contexto = $this->criarRede();
        $contexto['rede']->forceFill(['exigir_2fa' => true])->save();
        $funcionario = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa']);
        $this->actingAs($funcionario);

        $this->get(route('dashboard'))->assertRedirect(route('perfil.index', ['aba' => 'seguranca']));
        $this->get(route('perfil.index', ['aba' => 'seguranca']))->assertOk()->assertSee('Sua rede exige');

        // Configurando pelo caminho liberado, o sistema abre.
        $this->post(route('perfil.dois-fatores.iniciar'))->assertRedirect();
        $this->post(route('perfil.dois-fatores.confirmar'), ['codigo' => $this->codigoAtual($funcionario), 'senha_2fa' => 'password']);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_com_exigencia_quem_ja_tem_2fa_segue_normal(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->ativarDoisFatores($contexto['usuario']);
        $contexto['rede']->forceFill(['exigir_2fa' => true])->save();

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_admin_redefine_o_2fa_de_um_usuario(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $funcionario = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa']);
        $this->ativarDoisFatores($funcionario);

        $this->get(route('usuarios.edit', $funcionario))->assertOk()->assertSee('Redefinir 2FA');

        $this->post(route('usuarios.redefinir-2fa', $funcionario))->assertRedirect(route('usuarios.edit', $funcionario));

        $this->assertFalse($funcionario->refresh()->temDoisFatoresAtivo());
    }

    public function test_admin_nao_redefine_usuario_de_outra_rede(): void
    {
        $outra = $this->criarRede('outra');
        $this->ativarDoisFatores($outra['usuario']);
        $this->criarRedeAutenticada();

        // RedeTrait no binding: o usuario da outra rede nem e encontrado.
        $this->post(route('usuarios.redefinir-2fa', $outra['usuario']))->assertNotFound();

        $this->assertTrue($outra['usuario']->refresh()->temDoisFatoresAtivo());
    }

    public function test_nao_admin_com_permissao_de_editar_nao_redefine(): void
    {
        $contexto = $this->criarRede();
        $funcionario = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa']);
        $this->ativarDoisFatores($funcionario);

        $gerente = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Gerente');
        $gerente->givePermissionTo(['usuario.ver', 'usuario.editar']);

        $this->actingAs($gerente)->post(route('usuarios.redefinir-2fa', $funcionario))->assertForbidden();
        $this->assertTrue($funcionario->refresh()->temDoisFatoresAtivo());
    }

    /** O proprio 2FA se desliga no Meu Perfil, com senha — nao por este atalho. */
    public function test_admin_nao_redefine_o_proprio_2fa(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $this->ativarDoisFatores($contexto['usuario']);

        $this->post(route('usuarios.redefinir-2fa', $contexto['usuario']))->assertForbidden();
        $this->assertTrue($contexto['usuario']->refresh()->temDoisFatoresAtivo());
    }

    /** Defesa em profundidade: a politica de seguranca nao entra por mass-assignment. */
    public function test_exigencia_nao_entra_por_mass_assignment(): void
    {
        $rede = (new Rede)->fill(['nome' => 'X', 'exigir_2fa' => true]);

        $this->assertNull($rede->exigir_2fa);
    }

    /**
     * Derrubar o 2FA de um Admin e tomar a conta de quem manda na rede: so o
     * suporte faz, pelo comando `usuarios:redefinir-2fa` (com motivo na auditoria).
     */
    public function test_admin_nao_redefine_o_2fa_de_outro_admin(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $outroAdmin = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Admin');
        $this->ativarDoisFatores($outroAdmin);

        $this->post(route('usuarios.redefinir-2fa', $outroAdmin))->assertForbidden();
        $this->assertTrue($outroAdmin->refresh()->temDoisFatoresAtivo());
    }

    public function test_edicao_de_outro_admin_orienta_a_falar_com_o_suporte(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $outroAdmin = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Admin');
        $this->ativarDoisFatores($outroAdmin);

        $this->get(route('usuarios.edit', $outroAdmin))
            ->assertOk()
            ->assertDontSee('Redefinir 2FA')
            ->assertSee('fale com o suporte');
    }
}
