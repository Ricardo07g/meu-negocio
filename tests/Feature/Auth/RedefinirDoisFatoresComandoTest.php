<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\DoisFatores;
use Tests\TestCase;

/**
 * Suporte redefine o 2FA (ADR-0022): o caminho para o Admin que perdeu o celular
 * e os codigos — nenhum outro Admin da rede pode fazer isso pela tela.
 */
class RedefinirDoisFatoresComandoTest extends TestCase
{
    use DoisFatores;
    use RefreshDatabase;

    public function test_suporte_redefine_o_2fa_de_um_admin_e_registra_o_motivo(): void
    {
        $admin = $this->criarRede()['usuario'];
        $this->ativarDoisFatores($admin);

        $this->artisan('usuarios:redefinir-2fa', ['email' => $admin->email, '--motivo' => 'Chamado 123: perdeu o celular', '--operador' => 'ana.suporte'])
            ->expectsConfirmation("Redefinir a verificação em duas etapas de {$admin->nome} <{$admin->email}>?", 'yes')
            ->assertSuccessful();

        $this->assertFalse($admin->refresh()->temDoisFatoresAtivo());

        $registro = Activity::where('subject_id', $admin->id)->latest('id')->first();
        $this->assertSame('Verificação em duas etapas redefinida pelo suporte', $registro->description);
        $this->assertSame('Chamado 123: perdeu o celular', $registro->properties['motivo'] ?? null);
        // Quem agiu foi o suporte, nao o proprio Admin: sem causer, com o operador registrado.
        $this->assertNull($registro->causer_id);
        $this->assertSame('ana.suporte', $registro->properties['operador'] ?? null);
    }

    public function test_sem_confirmacao_nada_muda(): void
    {
        $admin = $this->criarRede()['usuario'];
        $this->ativarDoisFatores($admin);

        $this->artisan('usuarios:redefinir-2fa', ['email' => $admin->email, '--motivo' => 'teste'])
            ->expectsConfirmation("Redefinir a verificação em duas etapas de {$admin->nome} <{$admin->email}>?", 'no')
            ->assertFailed();

        $this->assertTrue($admin->refresh()->temDoisFatoresAtivo());
    }

    public function test_motivo_e_obrigatorio(): void
    {
        $admin = $this->criarRede()['usuario'];
        $this->ativarDoisFatores($admin);

        $this->artisan('usuarios:redefinir-2fa', ['email' => $admin->email])->assertFailed();

        $this->assertTrue($admin->refresh()->temDoisFatoresAtivo());
    }

    public function test_email_inexistente_falha(): void
    {
        $this->artisan('usuarios:redefinir-2fa', ['email' => 'ninguem@teste.com', '--motivo' => 'x'])
            ->expectsOutputToContain('Nenhum usuário')
            ->assertFailed();
    }

    public function test_usuario_sem_2fa_nao_tem_o_que_redefinir(): void
    {
        $admin = $this->criarRede()['usuario'];

        $this->artisan('usuarios:redefinir-2fa', ['email' => $admin->email, '--motivo' => 'x'])
            ->expectsOutputToContain('não tem a verificação em duas etapas ativa')
            ->assertFailed();
    }
}
