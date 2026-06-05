<?php

namespace Tests\Feature\Cadastro;

use App\Modules\Usuario\Models\Usuario;
use Database\Factories\ClienteFactory;
use Database\Factories\ProdutoFactory;
use Database\Factories\ServicoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * Smoke dos GET show das entidades de cadastro: Cliente, Servico, Produto,
 * PerfilAcesso (Role) e Usuario. As views de show carregam relacoes que
 * passaram pelo refactor pacote->etapas (ex.: ClienteController::show faz
 * load de 'vendasEtapas.servico'; ServicoController::show usa isEtapas() e
 * vendasEtapas()). Asserta 200 + view correta; tratarErro converteria um
 * residuo em redirect-back com 'erro', que NAO e 200.
 */
class ShowSmokeTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    public function test_show_de_cliente(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $cliente = ClienteFactory::new()->create(['rede_id' => $contexto['rede']->id]);

        $resp = $this->get(route('clientes.show', $cliente));

        $resp->assertOk();
        $resp->assertViewIs('cliente::show');
    }

    public function test_show_de_servico_avulso(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $servico = ServicoFactory::new()->avulso()->create(['rede_id' => $contexto['rede']->id]);

        $resp = $this->get(route('servicos.show', $servico));

        $resp->assertOk();
        $resp->assertViewIs('servico::show');
    }

    public function test_show_de_servico_em_etapas(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $servico = ServicoFactory::new()->etapas(5)->create(['rede_id' => $contexto['rede']->id]);

        $resp = $this->get(route('servicos.show', $servico));

        $resp->assertOk();
        $resp->assertViewIs('servico::show');
        $resp->assertViewHas('vendasEtapas');
    }

    public function test_show_de_produto(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $produto = ProdutoFactory::new()->create(['rede_id' => $contexto['rede']->id]);

        $resp = $this->get(route('produtos.show', $produto));

        $resp->assertOk();
        $resp->assertViewIs('produto::show');
    }

    public function test_show_de_perfil_acesso(): void
    {
        $this->criarRedeAutenticada();
        $perfil = Role::where('name', 'Admin')->firstOrFail();

        $resp = $this->get(route('perfis-acesso.show', $perfil));

        $resp->assertOk();
        $resp->assertViewIs('perfilacesso::show');
    }

    public function test_show_de_usuario(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $usuario = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa']);

        $resp = $this->get(route('usuarios.show', $usuario));

        $resp->assertOk();
        $resp->assertViewIs('usuario::show');
        $resp->assertViewHas('usuario', fn (Usuario $u) => $u->is($usuario));
    }
}
