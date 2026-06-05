<?php

declare(strict_types=1);

namespace Tests\Feature\Agenda;

use App\Modules\Tenant\Models\Empresa;
use App\Modules\Usuario\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Valida o scope Usuario::atendentesDaEmpresa usado pelas listagens de
 * atendentes da Agenda (index/json/edit).
 *
 * Regra: inclui usuarios com `atende=true` vinculados a empresa via pivot
 * `empresa_usuario` OU qualquer usuario com Role Admin (acesso a toda a rede).
 */
class AtendentesEscopoTest extends TestCase
{
    use RefreshDatabase;

    public function test_atendentes_da_empresa_respeita_pivot(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empA = $contexto['empresa'];
        $admin = $contexto['usuario']; // Admin, atende=true, acessa toda a rede

        $empB = Empresa::create([
            'rede_id' => $rede->id,
            'nome' => 'Empresa B',
        ]);

        // Profissional vinculado SOMENTE a empresa A.
        $profA = $this->criarUsuarioComum($rede, $empA, 'Profissional');

        // Profissional vinculado SOMENTE a empresa B.
        $profB = $this->criarUsuarioComum($rede, $empB, 'Profissional');

        // --- Atendentes da empresa A ---
        $idsA = Usuario::atendentesDaEmpresa($empA->id)->pluck('id')->sort()->values()->all();

        $this->assertContains($profA->id, $idsA, 'Profissional vinculado a A deveria aparecer.');
        $this->assertContains($admin->id, $idsA, 'Admin deveria aparecer em qualquer empresa da rede.');
        $this->assertNotContains($profB->id, $idsA, 'Profissional exclusivo de B nao deveria aparecer em A.');

        // --- Atendentes da empresa B ---
        $idsB = Usuario::atendentesDaEmpresa($empB->id)->pluck('id')->all();

        $this->assertContains($profB->id, $idsB, 'Profissional vinculado a B deveria aparecer.');
        $this->assertContains($admin->id, $idsB, 'Admin deveria aparecer em qualquer empresa da rede.');
        $this->assertNotContains($profA->id, $idsB, 'Profissional exclusivo de A nao deveria aparecer em B.');
    }

    public function test_usuario_que_nao_atende_fica_fora_da_lista(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empA = $contexto['empresa'];

        // Usuario vinculado a empresa A mas que NAO atende.
        $financeiro = $this->criarUsuarioComum($rede, $empA, 'Financeiro');
        $financeiro->update(['atende' => false]);

        $ids = Usuario::atendentesDaEmpresa($empA->id)->pluck('id')->all();

        $this->assertNotContains(
            $financeiro->id,
            $ids,
            'Usuario com atende=false nao deveria entrar na lista de atendentes.'
        );
    }
}
