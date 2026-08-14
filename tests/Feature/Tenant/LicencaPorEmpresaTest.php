<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Modules\Cliente\Models\Cliente;
use App\Modules\Produto\Models\{CategoriaProduto, Produto};
use App\Modules\Servico\Models\Servico;
use App\Modules\Tenant\Actions\EncerrarTrialAction;
use App\Modules\Tenant\Models\{Empresa, Plano};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * O plano e da EMPRESA (uma licenca por unidade), nao da rede — e o registro entrega
 * uma conta limpa, com a primeira unidade em teste gratuito no Pro.
 */
class LicencaPorEmpresaTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    public function test_registro_entrega_conta_enxuta_com_um_exemplo_de_cada(): void
    {
        $this->garantirSeedsBase();

        $this->post(route('registrar'), [
            'nome' => 'Ricardo Teste',
            'empresa' => 'Estudio Teste',
            'email' => 'ricardo@teste.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        // Catalogo de demonstracao poluido so gera faxina para quem acabou de entrar.
        $this->assertSame(1, CategoriaProduto::withoutGlobalScopes()->count());
        $this->assertSame(1, Produto::withoutGlobalScopes()->count());
        $this->assertSame(1, Servico::withoutGlobalScopes()->count());
        $this->assertSame(1, Cliente::withoutGlobalScopes()->count());
    }

    public function test_primeira_unidade_nasce_no_pro_em_teste_gratuito(): void
    {
        $this->garantirSeedsBase();

        $this->post(route('registrar'), [
            'nome' => 'Ricardo Teste',
            'empresa' => 'Estudio Teste',
            'email' => 'ricardo@teste.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $empresa = Empresa::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(Plano::PRO, $empresa->plano->slug);
        $this->assertTrue($empresa->emTrial());
        $this->assertSame(Empresa::DIAS_DE_TRIAL, $empresa->diasRestantesTrial());
    }

    public function test_unidade_contratada_depois_nao_ganha_trial(): void
    {
        $contexto = $this->criarRede();
        $segunda = $this->criarEmpresaExtra($contexto['rede']->id, 'Filial Centro');

        $this->assertFalse($segunda->emTrial());
        $this->assertNull($segunda->trial_expira_em);
    }

    public function test_comando_rebaixa_para_o_gratis_quando_o_teste_vence(): void
    {
        $contexto = $this->criarRede();
        $empresa = $contexto['empresa'];
        $venceuOntem = now()->subDay();
        $empresa->update(['trial_expira_em' => $venceuOntem]);

        $this->artisan('assinaturas:expirar-trial')->assertSuccessful();

        $empresa->refresh();
        $this->assertSame(Plano::GRATIS, $empresa->plano->slug);

        // A data vencida sobrevive: e o registro de que a unidade ja testou, e e o que
        // habilita o Admin a renovar o teste em vez de ficar num beco sem saida.
        $this->assertSame($venceuOntem->toDateString(), $empresa->trial_expira_em->toDateString());
        $this->assertTrue($empresa->trialVencido());
    }

    public function test_expirar_trial_e_idempotente_e_nao_reprocessa_unidade_ja_rebaixada(): void
    {
        $contexto = $this->criarRede();
        $empresa = $contexto['empresa'];
        $empresa->update(['trial_expira_em' => now()->subDay()]);

        $this->artisan('assinaturas:expirar-trial')->assertSuccessful();

        // Segunda passada: a unidade ja esta no Gratis, entao nao entra mais na query.
        $this->assertSame(0, app(EncerrarTrialAction::class)->executar());
    }

    public function test_trial_do_ultimo_dia_ainda_vale(): void
    {
        $contexto = $this->criarRede();
        $empresa = $contexto['empresa'];
        $empresa->update(['trial_expira_em' => now()]);

        $this->artisan('assinaturas:expirar-trial')->assertSuccessful();

        $empresa->refresh();
        $this->assertSame(Plano::PRO, $empresa->plano->slug);
        $this->assertTrue($empresa->emTrial());
    }

    public function test_middleware_rebaixa_o_trial_vencido_se_o_scheduler_falhar(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $empresa = $contexto['empresa'];
        $empresa->update(['trial_expira_em' => now()->subDay()]);

        // Qualquer request autenticado passa pelo VerificarEmpresa.
        $this->get(route('dashboard'))->assertOk();

        $this->assertSame(Plano::GRATIS, $empresa->refresh()->plano->slug);
    }

    public function test_financeiro_depende_da_licenca_da_unidade_em_contexto(): void
    {
        $contexto = $this->criarRede(planoSlug: Plano::GRATIS);
        $this->actingAs($contexto['usuario']);

        $unidadePro = $this->criarEmpresaExtra($contexto['rede']->id, 'Filial Pro');
        session(['empresas_atuais' => [$contexto['empresa']->id, $unidadePro->id]]);

        // Contexto na unidade Gratis: o modulo financeiro esta fora da licenca.
        // PlanoLimiteException e renderizada como redirect ao dashboard (bootstrap/app.php).
        session(['empresa_contexto_atual' => $contexto['empresa']->id]);
        $this->get(route('pagamentos.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('erro');

        // Mesma rede, mesmo usuario — mas a outra unidade tem licenca Pro.
        session(['empresa_contexto_atual' => $unidadePro->id]);
        $this->get(route('pagamentos.index'))->assertOk();
    }

    public function test_tenant_nao_contrata_nem_exclui_unidade(): void
    {
        $contexto = $this->criarRedeAutenticada();

        // Contratar unidade e ato comercial do operador: as rotas nao existem.
        $this->post('/empresas', ['nome' => 'Filial Pirata'])->assertMethodNotAllowed();
        $this->delete('/empresas/'.$contexto['empresa']->id)->assertMethodNotAllowed();
    }

    public function test_listagem_de_unidades_mostra_a_licenca_de_cada_uma(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $contexto['empresa']->update(['trial_expira_em' => now()->addDays(5)]);

        $outra = $this->criarEmpresaExtra($contexto['rede']->id, 'Filial Centro');
        session(['empresas_atuais' => [$contexto['empresa']->id, $outra->id]]);

        $resp = $this->get(route('empresas.index'));

        $resp->assertOk();
        $resp->assertSee('Filial Centro');
        $resp->assertSee('unidades licenciadas');
        $resp->assertSee('Pro');
    }
}
