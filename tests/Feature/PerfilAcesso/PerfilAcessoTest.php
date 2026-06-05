<?php

declare(strict_types=1);

namespace Tests\Feature\PerfilAcesso;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\{Permission, Role};
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Cobre o modulo PerfilAcesso (CRUD de Spatie\Role + atribuicao de
 * permissoes), exercitando os endpoints reais `perfis-acesso.*`.
 *
 * Admin (criarRedeAutenticada) tem todas as permissoes via PermissaoSeeder,
 * entao passa as Policies/Requests. O caso de 403 usa um usuario comum
 * sem `papel.*`.
 */
class PerfilAcessoTest extends TestCase
{
    use RefreshDatabase;

    private function esquecerCachePermissoes(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_lista_perfis(): void
    {
        $this->criarRedeAutenticada();

        $response = $this->get(route('perfis-acesso.index'));

        $response->assertOk();
        $response->assertViewIs('perfilacesso::index');
        $response->assertViewHas('perfis');
    }

    public function test_admin_cria_perfil_com_permissoes(): void
    {
        $this->criarRedeAutenticada();

        $permissoes = ['cliente.ver', 'cliente.criar'];

        $response = $this->post(route('perfis-acesso.store'), [
            'name' => 'Atendente Loja',
            'permissoes' => $permissoes,
        ]);

        $response->assertRedirect(route('perfis-acesso.index'));
        $response->assertSessionHas('sucesso');

        $this->esquecerCachePermissoes();

        $perfil = Role::where('name', 'Atendente Loja')->first();
        $this->assertNotNull($perfil);
        $this->assertEqualsCanonicalizing(
            $permissoes,
            $perfil->permissions->pluck('name')->all(),
        );
    }

    public function test_admin_cria_perfil_sem_permissoes(): void
    {
        $this->criarRedeAutenticada();

        $response = $this->post(route('perfis-acesso.store'), [
            'name' => 'Perfil Vazio',
        ]);

        $response->assertRedirect(route('perfis-acesso.index'));

        $this->esquecerCachePermissoes();
        $perfil = Role::where('name', 'Perfil Vazio')->first();
        $this->assertNotNull($perfil);
        $this->assertCount(0, $perfil->permissions);
    }

    public function test_admin_edita_permissoes_do_perfil(): void
    {
        $this->criarRedeAutenticada();

        $perfil = Role::create(['name' => 'Recepcao Teste', 'guard_name' => 'web']);
        $perfil->syncPermissions(['cliente.ver']);
        $this->esquecerCachePermissoes();

        $response = $this->put(route('perfis-acesso.update', $perfil), [
            'name' => 'Recepcao Teste',
            'permissoes' => ['cliente.ver', 'cliente.editar', 'agendamento.ver'],
        ]);

        $response->assertRedirect(route('perfis-acesso.index'));
        $response->assertSessionHas('sucesso');

        $this->esquecerCachePermissoes();
        $perfil->refresh();

        $this->assertEqualsCanonicalizing(
            ['cliente.ver', 'cliente.editar', 'agendamento.ver'],
            $perfil->permissions->pluck('name')->all(),
        );
    }

    public function test_admin_renomeia_perfil(): void
    {
        $this->criarRedeAutenticada();

        $perfil = Role::create(['name' => 'Nome Antigo', 'guard_name' => 'web']);
        $this->esquecerCachePermissoes();

        $response = $this->put(route('perfis-acesso.update', $perfil), [
            'name' => 'Nome Novo',
        ]);

        $response->assertRedirect(route('perfis-acesso.index'));
        $this->assertDatabaseHas('roles', ['id' => $perfil->id, 'name' => 'Nome Novo']);
        $this->assertDatabaseMissing('roles', ['name' => 'Nome Antigo']);
    }

    public function test_nome_duplicado_e_rejeitado_na_criacao(): void
    {
        $this->criarRedeAutenticada();

        // "Admin" ja existe (seed). Tentar recriar deve falhar na validacao unique.
        $response = $this->from(route('perfis-acesso.create'))
            ->post(route('perfis-acesso.store'), [
                'name' => 'Admin',
                'permissoes' => [],
            ]);

        $response->assertSessionHasErrors('name');
        // Continua havendo apenas um role "Admin".
        $this->assertSame(1, Role::where('name', 'Admin')->count());
    }

    public function test_nome_duplicado_e_rejeitado_na_edicao(): void
    {
        $this->criarRedeAutenticada();

        $existente = Role::create(['name' => 'Financeiro Teste', 'guard_name' => 'web']);
        $outro = Role::create(['name' => 'Marketing Teste', 'guard_name' => 'web']);
        $this->esquecerCachePermissoes();

        // Tentar renomear "Marketing Teste" para um nome ja usado.
        $response = $this->from(route('perfis-acesso.edit', $outro))
            ->put(route('perfis-acesso.update', $outro), [
                'name' => 'Financeiro Teste',
            ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseHas('roles', ['id' => $outro->id, 'name' => 'Marketing Teste']);
        $this->assertDatabaseHas('roles', ['id' => $existente->id, 'name' => 'Financeiro Teste']);
    }

    public function test_edicao_mantendo_proprio_nome_e_permitida(): void
    {
        $this->criarRedeAutenticada();

        $perfil = Role::create(['name' => 'Estoquista', 'guard_name' => 'web']);
        $perfil->syncPermissions(['estoque.ver']);
        $this->esquecerCachePermissoes();

        // Mesmo nome, so muda permissoes -> unique deve ignorar o proprio id.
        $response = $this->put(route('perfis-acesso.update', $perfil), [
            'name' => 'Estoquista',
            'permissoes' => ['estoque.ver', 'produto.ver'],
        ]);

        $response->assertSessionDoesntHaveErrors('name');
        $response->assertRedirect(route('perfis-acesso.index'));
    }

    public function test_permissao_inexistente_e_rejeitada(): void
    {
        $this->criarRedeAutenticada();

        $response = $this->from(route('perfis-acesso.create'))
            ->post(route('perfis-acesso.store'), [
                'name' => 'Perfil Invalido',
                'permissoes' => ['permissao.que.nao.existe'],
            ]);

        $response->assertSessionHasErrors('permissoes.0');
        $this->assertNull(Role::where('name', 'Perfil Invalido')->first());
    }

    public function test_usuario_sem_permissao_recebe_403_ao_listar(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // Papel comum sem `papel.*`.
        $papel = Role::firstOrCreate(['name' => 'Profissional', 'guard_name' => 'web']);
        $papel->syncPermissions(['cliente.ver']);
        $this->esquecerCachePermissoes();

        $comum = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Profissional');

        $this->actingAs($comum);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $this->get(route('perfis-acesso.index'))->assertForbidden();
    }

    public function test_usuario_sem_permissao_recebe_403_ao_criar(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $papel = Role::firstOrCreate(['name' => 'Profissional', 'guard_name' => 'web']);
        $papel->syncPermissions(['cliente.ver']);
        $this->esquecerCachePermissoes();

        $comum = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Profissional');

        $this->actingAs($comum);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $response = $this->post(route('perfis-acesso.store'), [
            'name' => 'Tentativa Indevida',
            'permissoes' => [],
        ]);

        $response->assertForbidden();
        $this->assertNull(Role::where('name', 'Tentativa Indevida')->first());
    }

    public function test_permissoes_agrupadas_disponiveis_no_create(): void
    {
        $this->criarRedeAutenticada();

        // Garante que ha permissoes seedadas para agrupar.
        $this->assertGreaterThan(0, Permission::count());

        $response = $this->get(route('perfis-acesso.create'));

        $response->assertOk();
        $response->assertViewHas('permissoes');
    }
}
