<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Tenant\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regressao: trocar para uma empresa diferente da default do usuario
 * (via filtro `?empresa_id=X` na listagem) NAO pode deslogar o usuario.
 *
 * Bug original: `Usuario` usava `EmpresaTrait`, que aplicava global scope
 * `WHERE empresa_id IN (contexto)` ao resolver `auth()->user()`. Quando
 * o contexto != user.empresa_id, o user retornava null e o auth middleware
 * redirecionava para /login.
 *
 * Correcao: removido `EmpresaTrait` de Usuario (modelo rede-level, acesso
 * empresa-level via pivot `empresa_usuario`).
 */
class TrocaEmpresaNaoDeslogaTest extends TestCase
{
    use RefreshDatabase;

    public function test_trocar_para_empresa_diferente_da_default_nao_desloga(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empA = $contexto['empresa']; // empresa default do user (empresa_id=$empA->id)

        $empB = Empresa::create([
            'rede_id' => $rede->id,
            'nome' => 'Empresa B',
        ]);

        // Usuario tem empresa_id default = empA, mas vai narrowar contexto para empB.
        session(['empresas_atuais' => [$empA->id, $empB->id]]);

        $resp = $this->get(route('vendas.index', ['empresa_id' => $empB->id]));
        $resp->assertOk();
        $this->assertSame($empB->id, session('empresa_contexto_atual'));

        $resp2 = $this->get(route('vendas.index', ['empresa_id' => $empB->id]));
        $resp2->assertOk();

        $resp3 = $this->get(route('vendas.index', ['empresa_id' => $empA->id]));
        $resp3->assertOk();
    }

    public function test_acessar_listagem_apos_contexto_persistido_em_sessao(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empA = $contexto['empresa'];

        $empB = Empresa::create([
            'rede_id' => $rede->id,
            'nome' => 'Empresa B',
        ]);

        // Simula estado herdado de uma navegacao anterior — contexto em empB.
        session([
            'empresas_atuais' => [$empA->id, $empB->id],
            'empresa_contexto_atual' => $empB->id,
        ]);

        // Sem param na URL: a session ja tem contexto. Auth deve continuar valido
        // mesmo o user.empresa_id sendo empA != contexto empB.
        $resp = $this->get(route('vendas.index'));
        $resp->assertOk();
    }
}
