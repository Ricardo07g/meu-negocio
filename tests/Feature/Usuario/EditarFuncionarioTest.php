<?php

declare(strict_types=1);

namespace Tests\Feature\Usuario;

use App\Modules\Usuario\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * Regressão: Admin recebia 403 ao editar funcionário.
 *
 * `UsuarioPolicy::update` autorizava por `podeAcessarEmpresa($alvo->empresa_id)`
 * — a empresa **default** do alvo. Duas coisas erradas de uma vez: `empresa_id`
 * é preferência de login (o acesso real é o pivot `empresa_usuario`), e o
 * formulário de usuário nem tem esse campo, então todo funcionário criado pela
 * tela nascia com a coluna nula. Como `podeAcessarEmpresa(null)` devolve false
 * ANTES de olhar o papel, nem o Admin passava — o 403 batia justamente em quem
 * tem acesso a tudo.
 */
class EditarFuncionarioTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    /** @param array{rede: mixed, empresa: mixed, usuario: mixed} $contexto */
    private function funcionarioSemEmpresaDefault(array $contexto, string $papel = 'Recepcao'): Usuario
    {
        $funcionario = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], $papel);

        // O estado que a tela produz: pivot preenchido, default nulo.
        $funcionario->update(['empresa_id' => null]);
        $funcionario->empresas()->sync([
            $contexto['empresa']->id => ['rede_id' => $contexto['rede']->id],
        ]);

        return $funcionario->fresh();
    }

    public function test_admin_edita_funcionario_sem_empresa_default(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $funcionario = $this->funcionarioSemEmpresaDefault($contexto);

        $this->assertNull($funcionario->empresa_id, 'O cenario do bug exige o default nulo.');

        $this->get(route('usuarios.edit', $funcionario))->assertOk();
    }

    public function test_admin_salva_funcionario_sem_empresa_default(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $funcionario = $this->funcionarioSemEmpresaDefault($contexto);

        $resp = $this->put(route('usuarios.update', $funcionario), [
            'nome' => 'Nome Corrigido',
            'email' => $funcionario->email,
            'papel' => 'Recepcao',
            'empresas' => [$contexto['empresa']->id],
        ]);

        $resp->assertRedirect(route('usuarios.index'));
        $this->assertSame('Nome Corrigido', $funcionario->fresh()->nome);
    }

    /** Salvar preenche o default que faltava — o dado para de nascer torto. */
    public function test_salvar_preenche_a_empresa_default_a_partir_do_acesso(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $funcionario = $this->funcionarioSemEmpresaDefault($contexto);

        $this->put(route('usuarios.update', $funcionario), [
            'nome' => $funcionario->nome,
            'email' => $funcionario->email,
            'papel' => 'Recepcao',
            'empresas' => [$contexto['empresa']->id],
        ])->assertRedirect();

        $this->assertSame(
            $contexto['empresa']->id,
            $funcionario->fresh()->empresa_id,
            'Sem empresa default o usuario nao tem unidade para abrir no login.'
        );
    }

    public function test_funcionario_novo_nasce_com_empresa_default(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // Perfis nao-Admin sao criados pela UI de perfis de acesso; aqui basta existir.
        Role::findOrCreate('Recepcao', 'web');

        $this->post(route('usuarios.store'), [
            'nome' => 'Funcionario Novo',
            'email' => 'novo@teste.com',
            'password' => 'senha12345',
            'papel' => 'Recepcao',
            'empresas' => [$contexto['empresa']->id],
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('usuarios.index'));

        $novo = Usuario::where('email', 'novo@teste.com')->firstOrFail();
        $this->assertSame($contexto['empresa']->id, $novo->empresa_id);
    }

    /** A fronteira que continua valendo: rede. */
    public function test_admin_nao_edita_usuario_de_outra_rede(): void
    {
        $outra = $this->criarRede('outra');
        $this->criarRedeAutenticada();

        $this->get(route('usuarios.edit', $outra['usuario']))->assertNotFound();
    }

    /**
     * Não-admin continua limitado às suas unidades: a correção do 403 do Admin
     * não podia abrir a porta para todo mundo.
     */
    public function test_nao_admin_nao_edita_usuario_de_outra_empresa(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $empresaB = $this->criarEmpresaExtra($contexto['rede']->id, 'Empresa B');

        $gerente = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Gerente');
        $gerente->givePermissionTo(['usuario.ver', 'usuario.editar']);

        $deOutraUnidade = $this->criarUsuarioComum($contexto['rede'], $empresaB, 'Recepcao');
        $deOutraUnidade->empresas()->sync([$empresaB->id => ['rede_id' => $contexto['rede']->id]]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($gerente);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $this->get(route('usuarios.edit', $deOutraUnidade))->assertForbidden();
    }
}
