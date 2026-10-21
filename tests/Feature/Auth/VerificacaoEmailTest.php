<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Mail\VerificacaoEmailMailable;
use App\Modules\Usuario\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail, URL};
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Verificacao de e-mail (ADR-0022): cadastro novo — pelo registro ou pelo Admin —
 * so entra no sistema depois de clicar no link assinado.
 */
class VerificacaoEmailTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{rede: mixed, empresa: mixed, usuario: Usuario} */
    private function redeComAdminNaoVerificado(): array
    {
        $contexto = $this->criarRede();
        $contexto['usuario']->forceFill(['email_verified_at' => null])->save();

        return $contexto;
    }

    private function linkDeVerificacao(Usuario $usuario, ?string $hash = null, int $minutos = 60): string
    {
        return URL::temporarySignedRoute(
            'verificacao.verificar',
            now()->addMinutes($minutos),
            ['id' => $usuario->id, 'hash' => $hash ?? sha1($usuario->email)],
        );
    }

    public function test_registro_envia_o_link_e_para_na_tela_de_confirmacao(): void
    {
        Mail::fake();
        $this->garantirSeedsBase();

        $this->post(route('registrar'), [
            'nome' => 'Maria',
            'email' => 'maria@teste.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
            'empresa' => 'Estudio Maria',
        ])->assertRedirect(route('verificacao.aviso'));

        $usuario = Usuario::withoutGlobalScopes()->firstWhere('email', 'maria@teste.com');
        $this->assertNull($usuario->email_verified_at);
        Mail::assertSent(VerificacaoEmailMailable::class, fn ($m) => $m->hasTo('maria@teste.com'));
    }

    public function test_falha_no_envio_nao_derruba_o_registro(): void
    {
        $this->garantirSeedsBase();
        Mail::shouldReceive('to')->once()->andThrow(new TransportException('conexão descartada'));

        $this->post(route('registrar'), [
            'nome' => 'Maria',
            'email' => 'maria@teste.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
            'empresa' => 'Estudio Maria',
        ])->assertRedirect(route('verificacao.aviso'));

        $this->assertAuthenticated();
        $this->assertTrue(Usuario::withoutGlobalScopes()->where('email', 'maria@teste.com')->exists());
    }

    public function test_nao_verificado_e_barrado_no_sistema(): void
    {
        $contexto = $this->redeComAdminNaoVerificado();

        $this->actingAs($contexto['usuario']);

        $this->get(route('dashboard'))->assertRedirect(route('verificacao.aviso'));
        $this->get(route('perfil.index'))->assertRedirect(route('verificacao.aviso'));
        $this->get(route('verificacao.aviso'))->assertOk()->assertSee($contexto['usuario']->email);
    }

    public function test_verificado_nao_ve_a_tela_de_confirmacao(): void
    {
        $contexto = $this->criarRede();

        $this->actingAs($contexto['usuario'])
            ->get(route('verificacao.aviso'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_link_assinado_confirma_o_email(): void
    {
        $contexto = $this->redeComAdminNaoVerificado();

        $this->actingAs($contexto['usuario'])
            ->get($this->linkDeVerificacao($contexto['usuario']))
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($contexto['usuario']->refresh()->email_verified_at);
    }

    public function test_link_adulterado_e_recusado(): void
    {
        $contexto = $this->redeComAdminNaoVerificado();

        $this->actingAs($contexto['usuario'])
            ->get($this->linkDeVerificacao($contexto['usuario']).'x')
            ->assertForbidden();

        $this->assertNull($contexto['usuario']->refresh()->email_verified_at);
    }

    public function test_link_expirado_e_recusado(): void
    {
        $contexto = $this->redeComAdminNaoVerificado();
        $link = $this->linkDeVerificacao($contexto['usuario']);

        $this->travel(61)->minutes();

        $this->actingAs($contexto['usuario'])->get($link)->assertForbidden();
        $this->assertNull($contexto['usuario']->refresh()->email_verified_at);
    }

    /** Link valido de um e-mail anterior nao confirma o endereco atual. */
    public function test_link_com_hash_de_outro_email_e_recusado(): void
    {
        $contexto = $this->redeComAdminNaoVerificado();

        $this->actingAs($contexto['usuario'])
            ->get($this->linkDeVerificacao($contexto['usuario'], sha1('antigo@teste.com')))
            ->assertForbidden();

        $this->assertNull($contexto['usuario']->refresh()->email_verified_at);
    }

    /** O link de um usuario nao verifica outro, nem com assinatura valida. */
    public function test_link_de_outro_usuario_e_recusado(): void
    {
        $contexto = $this->redeComAdminNaoVerificado();
        $outro = $this->redeComAdminNaoVerificado()['usuario'];

        $this->actingAs($contexto['usuario'])
            ->get($this->linkDeVerificacao($outro))
            ->assertForbidden();

        $this->assertNull($outro->refresh()->email_verified_at);
        $this->assertNull($contexto['usuario']->refresh()->email_verified_at);
    }

    public function test_link_sem_login_manda_para_o_login(): void
    {
        $contexto = $this->redeComAdminNaoVerificado();

        $this->get($this->linkDeVerificacao($contexto['usuario']))->assertRedirect(route('login'));
        $this->assertNull($contexto['usuario']->refresh()->email_verified_at);
    }

    public function test_reenviar_manda_um_novo_link(): void
    {
        Mail::fake();
        $contexto = $this->redeComAdminNaoVerificado();

        $this->actingAs($contexto['usuario'])
            ->from(route('verificacao.aviso'))
            ->post(route('verificacao.reenviar'))
            ->assertRedirect(route('verificacao.aviso'))
            ->assertSessionHas('sucesso');

        Mail::assertSent(VerificacaoEmailMailable::class, 1);
    }

    public function test_reenviar_respeita_o_limite_por_minuto(): void
    {
        Mail::fake();
        $contexto = $this->redeComAdminNaoVerificado();
        $this->actingAs($contexto['usuario']);

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('verificacao.reenviar'))->assertRedirect();
        }

        $this->post(route('verificacao.reenviar'))->assertStatus(429);
        Mail::assertSent(VerificacaoEmailMailable::class, 6);
    }

    public function test_usuario_criado_pelo_admin_recebe_o_link(): void
    {
        Mail::fake();
        $contexto = $this->criarRedeAutenticada();
        $this->garantirRole('Recepcao');

        $this->post(route('usuarios.store'), [
            'nome' => 'Atendente Novo',
            'email' => 'novo@teste.com',
            'password' => 'senha-forte-123',
            'papel' => 'Recepcao',
            'empresas' => [$contexto['empresa']->id],
        ])->assertRedirect(route('usuarios.index'));

        $novo = Usuario::firstWhere('email', 'novo@teste.com');
        $this->assertNull($novo->email_verified_at);
        Mail::assertSent(VerificacaoEmailMailable::class, fn ($m) => $m->hasTo('novo@teste.com'));
    }

    public function test_admin_trocar_o_email_do_funcionario_zera_a_verificacao(): void
    {
        Mail::fake();
        $contexto = $this->criarRedeAutenticada();
        $funcionario = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Recepcao');

        $this->put(route('usuarios.update', $funcionario), [
            'nome' => $funcionario->nome,
            'email' => 'trocado@teste.com',
            'papel' => 'Recepcao',
            'empresas' => [$contexto['empresa']->id],
        ])->assertRedirect(route('usuarios.index'));

        $this->assertNull($funcionario->refresh()->email_verified_at);
        Mail::assertSent(VerificacaoEmailMailable::class, fn ($m) => $m->hasTo('trocado@teste.com'));
    }

    public function test_editar_sem_trocar_o_email_nao_reenvia(): void
    {
        Mail::fake();
        $contexto = $this->criarRedeAutenticada();
        $funcionario = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Recepcao');

        $this->put(route('usuarios.update', $funcionario), [
            'nome' => 'Outro Nome',
            'email' => $funcionario->email,
            'papel' => 'Recepcao',
            'empresas' => [$contexto['empresa']->id],
        ])->assertRedirect(route('usuarios.index'));

        $this->assertNotNull($funcionario->refresh()->email_verified_at);
        Mail::assertNothingSent();
    }

    public function test_email_carrega_o_link_assinado_e_o_nome(): void
    {
        $contexto = $this->redeComAdminNaoVerificado();
        Mail::fake();

        $contexto['usuario']->sendEmailVerificationNotification();

        Mail::assertSent(VerificacaoEmailMailable::class, function (VerificacaoEmailMailable $m) use ($contexto) {
            $html = $m->render();

            return str_contains($m->urlVerificacao, '/verificar-email/'.$contexto['usuario']->id.'/')
                && str_contains($m->urlVerificacao, 'signature=')
                && str_contains($html, e($contexto['usuario']->nome));
        });
    }
}
