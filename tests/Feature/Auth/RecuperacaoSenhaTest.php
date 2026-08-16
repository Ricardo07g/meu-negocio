<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Mail\RecuperacaoSenhaMailable;
use App\Modules\Usuario\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash, Mail, Password};
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * Recuperação de senha, ponta a ponta.
 *
 * O fluxo não tinha teste nenhum — e o defeito que isso escondeu foi silencioso
 * do pior jeito: `RedefinirSenhaController::reset` termina com
 * `redirect()->route('login')->with('sucesso', ...)`, mas o login não renderizava
 * flash. A senha era trocada de verdade e o usuário caía numa tela muda, sem
 * distinguir "deu certo" de "não aconteceu nada".
 */
class RecuperacaoSenhaTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    private function usuario(string $senha = 'senha-antiga-123'): Usuario
    {
        $contexto = $this->criarRede();

        return Usuario::create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'nome' => 'Quem Esqueceu',
            'email' => 'esqueci@teste.com',
            'password' => $senha,
            'ativo' => true,
            'atende' => false,
        ]);
    }

    public function test_tela_de_recuperacao_responde(): void
    {
        $this->get(route('senha.solicitar'))
            ->assertOk()
            ->assertSee('Esqueci minha senha');
    }

    public function test_pedido_envia_o_email_e_cria_o_token(): void
    {
        Mail::fake();
        $usuario = $this->usuario();

        $this->post(route('senha.solicitar.enviar'), ['email' => $usuario->email])
            ->assertRedirect()
            ->assertSessionHas('sucesso');

        Mail::assertSent(RecuperacaoSenhaMailable::class, fn ($mail) => $mail->hasTo($usuario->email));

        $this->assertSame(1, DB::table('password_reset_tokens')->where('email', $usuario->email)->count());
    }

    /** Segurança: a resposta não pode revelar se o email existe. */
    public function test_email_inexistente_recebe_a_mesma_mensagem(): void
    {
        Mail::fake();
        $this->usuario();

        $resp = $this->post(route('senha.solicitar.enviar'), ['email' => 'ninguem@teste.com']);

        $resp->assertRedirect()->assertSessionHas('sucesso');
        $resp->assertSessionMissing('erro');
        Mail::assertNothingSent();
    }

    public function test_email_carrega_o_link_com_token_e_destinatario(): void
    {
        $usuario = $this->usuario();
        $token = Password::broker()->createToken($usuario);

        $html = (new RecuperacaoSenhaMailable($token, $usuario->email))->render();

        $this->assertStringContainsString(
            route('senha.redefinir.form', ['token' => $token, 'email' => $usuario->email]),
            html_entity_decode($html),
            'Sem o link certo o email não serve para nada.'
        );
    }

    public function test_formulario_de_redefinicao_vem_com_o_email_do_link(): void
    {
        $usuario = $this->usuario();
        $token = Password::broker()->createToken($usuario);

        $this->get(route('senha.redefinir.form', ['token' => $token, 'email' => $usuario->email]))
            ->assertOk()
            ->assertSee($usuario->email, false)
            ->assertSee($token, false);
    }

    /**
     * O caso que estava quebrado: a senha trocava, mas a tela não dizia nada.
     */
    public function test_redefinir_troca_a_senha_e_confirma_na_tela_de_login(): void
    {
        $usuario = $this->usuario();
        $token = Password::broker()->createToken($usuario);

        $this->followingRedirects()
            ->post(route('senha.redefinir'), [
                'token' => $token,
                'email' => $usuario->email,
                'password' => 'senha-nova-98765',
                'password_confirmation' => 'senha-nova-98765',
            ])
            ->assertOk()
            ->assertSee('Senha redefinida com sucesso');

        $this->assertTrue(
            Hash::check('senha-nova-98765', $usuario->fresh()->password),
            'A senha precisa ter sido trocada de fato.'
        );
    }

    public function test_login_funciona_com_a_senha_nova(): void
    {
        $usuario = $this->usuario();
        $token = Password::broker()->createToken($usuario);

        $this->post(route('senha.redefinir'), [
            'token' => $token,
            'email' => $usuario->email,
            'password' => 'senha-nova-98765',
            'password_confirmation' => 'senha-nova-98765',
        ])->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email' => $usuario->email,
            'password' => 'senha-nova-98765',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_token_invalido_e_recusado(): void
    {
        $usuario = $this->usuario();
        Password::broker()->createToken($usuario);

        $this->post(route('senha.redefinir'), [
            'token' => 'token-inventado',
            'email' => $usuario->email,
            'password' => 'senha-nova-98765',
            'password_confirmation' => 'senha-nova-98765',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(
            Hash::check('senha-antiga-123', $usuario->fresh()->password),
            'Token invalido nao pode trocar senha nenhuma.'
        );
    }

    public function test_token_nao_serve_duas_vezes(): void
    {
        $usuario = $this->usuario();
        $token = Password::broker()->createToken($usuario);

        $dados = [
            'token' => $token,
            'email' => $usuario->email,
            'password' => 'senha-nova-98765',
            'password_confirmation' => 'senha-nova-98765',
        ];

        $this->post(route('senha.redefinir'), $dados)->assertRedirect(route('login'));

        $this->post(route('senha.redefinir'), array_merge($dados, [
            'password' => 'terceira-senha-321',
            'password_confirmation' => 'terceira-senha-321',
        ]))->assertSessionHasErrors('email');

        $this->assertTrue(
            Hash::check('senha-nova-98765', $usuario->fresh()->password),
            'A segunda tentativa com o mesmo token nao pode valer.'
        );
    }

    public function test_confirmacao_divergente_e_senha_curta_sao_recusadas(): void
    {
        $usuario = $this->usuario();
        $token = Password::broker()->createToken($usuario);

        $this->post(route('senha.redefinir'), [
            'token' => $token,
            'email' => $usuario->email,
            'password' => 'senha-nova-98765',
            'password_confirmation' => 'outra-coisa-123',
        ])->assertSessionHasErrors('password');

        $this->post(route('senha.redefinir'), [
            'token' => $token,
            'email' => $usuario->email,
            'password' => 'curta',
            'password_confirmation' => 'curta',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('senha-antiga-123', $usuario->fresh()->password));
    }
}
