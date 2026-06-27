<?php

declare(strict_types=1);

namespace Tests\Feature\Usuario;

use App\Modules\Usuario\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

class InativacaoTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    public function test_admin_inativa_usuario_pela_edicao(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $comum = $this->criarUsuarioComum($rede, $contexto['empresa'], 'Recepcao');

        $resp = $this->put(route('usuarios.update', $comum), [
            'nome' => $comum->nome,
            'email' => $comum->email,
            'papel' => 'Recepcao',
            'empresas' => [$contexto['empresa']->id],
            'ativo' => 0,
        ]);

        $resp->assertRedirect(route('usuarios.index'));
        $resp->assertSessionHas('sucesso');
        $this->assertFalse((bool) $comum->fresh()->ativo);
        // O usuario inativo deixa de ocupar vaga: so o admin conta.
        $this->assertSame(1, $rede->usuariosAtivos()->count());
    }

    public function test_nao_pode_inativar_a_propria_conta(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $admin = $contexto['usuario'];

        $resp = $this->put(route('usuarios.update', $admin), [
            'nome' => $admin->nome,
            'email' => $admin->email,
            'papel' => 'Admin',
            'ativo' => 0,
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('erro');
        $this->assertTrue((bool) $admin->fresh()->ativo, 'O admin nao pode inativar a si mesmo.');
    }

    public function test_nao_pode_inativar_o_ultimo_admin_ativo(): void
    {
        $contexto = $this->criarRede();
        $rede = $contexto['rede'];
        $admin = $contexto['usuario'];

        // Editor nao-admin com permissao de editar usuarios.
        $editor = $this->criarUsuarioComum($rede, $contexto['empresa'], 'Gerente');
        $editor->givePermissionTo('usuario.editar');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($editor);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $resp = $this->put(route('usuarios.update', $admin), [
            'nome' => $admin->nome,
            'email' => $admin->email,
            'papel' => 'Admin',
            'ativo' => 0,
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('erro');
        $this->assertTrue((bool) $admin->fresh()->ativo, 'A rede precisa de ao menos um admin ativo.');
    }

    public function test_form_de_edicao_mostra_o_toggle_ativo(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $comum = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Recepcao');

        $resp = $this->get(route('usuarios.edit', $comum));

        $resp->assertOk();
        $resp->assertSee('id="ativo"', false);
    }

    public function test_usuario_inativo_nao_consegue_logar(): void
    {
        $contexto = $this->criarRede();
        $comum = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Recepcao');
        $comum->update(['ativo' => false]);

        $resp = $this->post(route('login'), [
            'email' => $comum->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $resp->assertSessionHasErrors('email');
    }
}
