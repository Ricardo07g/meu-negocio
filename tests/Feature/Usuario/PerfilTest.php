<?php

declare(strict_types=1);

namespace Tests\Feature\Usuario;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cobre o modulo Meu Perfil — self-service de atualizacao de dados
 * pessoais e troca de senha (FECH-012).
 *
 * Sao os dois fluxos relevantes:
 *   1. Atualizar nome/email do proprio usuario.
 *   2. Trocar senha exigindo a senha atual.
 *
 * Nao reutiliza SalvarUsuarioRequest — perfil e tela self e tem
 * regras diferentes (sem perfil_acesso, sem empresas).
 */
class PerfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_atualiza_nome_e_email_do_proprio_usuario(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $response = $this->post(route('perfil.atualizar'), [
            'nome' => 'Ricardo Atualizado',
            'email' => 'ricardo.novo@teste.com',
        ]);

        $response->assertRedirect(route('perfil.index'));
        $response->assertSessionHas('sucesso');

        $contexto['usuario']->refresh();
        $this->assertSame('Ricardo Atualizado', $contexto['usuario']->nome);
        $this->assertSame('ricardo.novo@teste.com', $contexto['usuario']->email);
    }

    public function test_trocar_senha_exige_senha_atual_correta(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // Senha atual errada — falha
        $this->from(route('perfil.index'))
            ->post(route('perfil.senha'), [
                'senha_atual' => 'senha-incorreta',
                'password' => 'novasenha123',
                'password_confirmation' => 'novasenha123',
            ])
            ->assertRedirect(route('perfil.index'))
            ->assertSessionHasErrors('senha_atual');

        $contexto['usuario']->refresh();
        $this->assertTrue(Hash::check('password', $contexto['usuario']->password));

        // Senha atual correta — sucesso
        $this->post(route('perfil.senha'), [
            'senha_atual' => 'password',
            'password' => 'novasenha123',
            'password_confirmation' => 'novasenha123',
        ])->assertRedirect(route('perfil.index'))->assertSessionHas('sucesso');

        $contexto['usuario']->refresh();
        $this->assertTrue(Hash::check('novasenha123', $contexto['usuario']->password));
    }
}
