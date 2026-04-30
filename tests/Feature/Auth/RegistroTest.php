<?php

namespace Tests\Feature\Auth;

use App\Modules\Tenant\Models\Empresa;
use App\Modules\Tenant\Models\Rede;
use App\Modules\Usuario\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre o fluxo completo de auto-registro: criar rede + empresa + admin
 * + login automatico ate o dashboard.
 */
class RegistroTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_cria_conta_e_loga(): void
    {
        $this->garantirSeedsBase();

        $response = $this->post('/registrar', [
            'nome' => 'Maria Empreendedora',
            'email' => 'maria@meunegocio.test',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
            'empresa' => 'Estudio Maria',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();

        $usuario = Usuario::firstWhere('email', 'maria@meunegocio.test');
        $this->assertNotNull($usuario, 'Usuario deveria ter sido criado.');
        $this->assertTrue($usuario->hasRole('Admin'), 'Auto-registro deve atribuir papel Admin.');

        $rede = Rede::find($usuario->rede_id);
        $this->assertNotNull($rede, 'Rede deveria ter sido criada para o admin.');
        $this->assertSame('Estudio Maria', $rede->nome);

        $empresa = Empresa::where('rede_id', $rede->id)->first();
        $this->assertNotNull($empresa, 'Empresa default deveria ter sido criada para a rede.');
        $this->assertSame($empresa->id, $usuario->empresa_id);
    }
}
