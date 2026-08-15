<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Exceptions\NegocioException;
use App\Modules\Tenant\Actions\RenovarTrialAction;
use App\Modules\Tenant\Models\{Empresa, Fatura, Plano};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * A licenca e da EMPRESA: a troca de plano opera sobre uma unidade, e a fatura da rede
 * e a soma das licencas cobraveis (unidade em teste gratuito nao entra).
 */
class AssinaturaTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    public function test_admin_ve_pagina_de_assinatura_sem_fabricar_fatura(): void
    {
        $this->criarRedeAutenticada();

        $resp = $this->get(route('assinatura.index'));

        $resp->assertOk();
        $resp->assertViewIs('tenant::assinatura');

        // Abrir a tela e um GET: nao pode escrever no banco (o antigo
        // garantirHistoricoFaturas fabricava meses retroativos na leitura).
        $this->assertDatabaseCount('faturas', 0);
    }

    public function test_usuario_comum_nao_ve_a_pagina_de_assinatura(): void
    {
        $contexto = $this->criarRede();
        $comum = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Recepcao');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($comum);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        // Preco, fatura e teste gratuito sao assunto do dono da conta: so o Admin ve.
        $this->get(route('assinatura.index'))->assertForbidden();
    }

    public function test_aviso_de_teste_no_layout_so_aparece_para_o_admin(): void
    {
        $contexto = $this->criarRede();
        $contexto['empresa']->update(['trial_expira_em' => now()->addDays(5)]);

        $comum = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Recepcao');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($comum);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Teste grátis do plano Pro', escape: false);

        $this->actingAs($contexto['usuario']);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Teste grátis do plano Pro', escape: false);
    }

    public function test_admin_faz_upgrade_da_unidade_e_ajusta_fatura_pro_rata(): void
    {
        $contexto = $this->criarRede(planoSlug: Plano::GRATIS);
        $this->actingAs($contexto['usuario']);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $empresa = $contexto['empresa'];
        $gratis = Plano::where('slug', Plano::GRATIS)->firstOrFail();
        $pro = Plano::where('slug', Plano::PRO)->firstOrFail();

        $resp = $this->post(route('assinatura.transicionar'), [
            'empresa_id' => $empresa->id,
            'plano_id' => $pro->id,
        ]);

        $resp->assertRedirect(route('assinatura.index'));
        $resp->assertSessionHas('sucesso');
        $this->assertSame($pro->id, $empresa->fresh()->plano_id);

        $hoje = Carbon::now();
        $dias = $hoje->daysInMonth;
        $usados = $hoje->day - 1;
        $restantes = $dias - $usados;
        $esperado = round(
            ((float) $gratis->preco_por_licenca * $usados + (float) $pro->preco_por_licenca * $restantes) / $dias,
            2
        );

        $fatura = Fatura::where('rede_id', $contexto['rede']->id)
            ->where('referencia', $hoje->format('Y-m'))
            ->first();

        $this->assertNotNull($fatura);
        $this->assertEqualsWithDelta($esperado, (float) $fatura->valor, 0.01);
    }

    public function test_fatura_soma_as_demais_licencas_cobraveis_da_rede(): void
    {
        $contexto = $this->criarRede(planoSlug: Plano::GRATIS);
        $this->actingAs($contexto['usuario']);

        // Segunda unidade ja no Pro (contratada, sem trial): entra inteira na fatura.
        $outra = $this->criarEmpresaExtra($contexto['rede']->id, 'Filial Centro');
        session(['empresas_atuais' => [$contexto['empresa']->id, $outra->id]]);

        $pro = Plano::where('slug', Plano::PRO)->firstOrFail();

        $this->post(route('assinatura.transicionar'), [
            'empresa_id' => $contexto['empresa']->id,
            'plano_id' => $pro->id,
        ])->assertRedirect();

        $fatura = Fatura::where('rede_id', $contexto['rede']->id)->firstOrFail();

        // A unidade que mudou entra rateada; a outra entra pelo mes cheio.
        $this->assertGreaterThan((float) $pro->preco_por_licenca, (float) $fatura->valor);
    }

    public function test_troca_ajusta_a_fatura_em_aberto_ja_existente(): void
    {
        $contexto = $this->criarRede(planoSlug: Plano::GRATIS);
        $this->actingAs($contexto['usuario']);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $gratis = Plano::where('slug', Plano::GRATIS)->firstOrFail();
        $pro = Plano::where('slug', Plano::PRO)->firstOrFail();

        $ref = Carbon::now()->format('Y-m');
        $faturaExistente = Fatura::create([
            'rede_id' => $contexto['rede']->id,
            'plano_id' => $gratis->id,
            'referencia' => $ref,
            'valor' => 0,
            'vencimento' => Carbon::now()->endOfMonth(),
            'status' => 'em_aberto',
        ]);

        $this->post(route('assinatura.transicionar'), [
            'empresa_id' => $contexto['empresa']->id,
            'plano_id' => $pro->id,
        ])->assertRedirect();

        // Mesma fatura (sem duplicar — unique rede+referencia), agora com valor.
        $this->assertSame(1, Fatura::where('rede_id', $contexto['rede']->id)->where('referencia', $ref)->count());
        $this->assertGreaterThan(0, (float) $faturaExistente->fresh()->valor);
    }

    public function test_downgrade_bloqueado_quando_excede_assentos_da_unidade(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $empresa = $contexto['empresa'];
        $pro = Plano::where('slug', Plano::PRO)->firstOrFail();
        $gratis = Plano::where('slug', Plano::GRATIS)->firstOrFail();

        // Gratis permite 2 assentos; com admin + 2 comuns a unidade fica com 3.
        $this->criarUsuarioComum($contexto['rede'], $empresa, 'Recepcao');
        $this->criarUsuarioComum($contexto['rede'], $empresa, 'Profissional');

        $resp = $this->post(route('assinatura.transicionar'), [
            'empresa_id' => $empresa->id,
            'plano_id' => $gratis->id,
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('erro');
        $this->assertSame($pro->id, $empresa->fresh()->plano_id, 'O plano nao deve mudar quando o limite e violado.');
    }

    public function test_segunda_licenca_gratis_na_mesma_rede_e_rejeitada(): void
    {
        $contexto = $this->criarRede(planoSlug: Plano::GRATIS);
        $this->actingAs($contexto['usuario']);

        $outra = $this->criarEmpresaExtra($contexto['rede']->id, 'Filial Centro');
        session(['empresas_atuais' => [$contexto['empresa']->id, $outra->id]]);

        $gratis = Plano::where('slug', Plano::GRATIS)->firstOrFail();

        $resp = $this->post(route('assinatura.transicionar'), [
            'empresa_id' => $outra->id,
            'plano_id' => $gratis->id,
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('erro');
        $this->assertNotSame($gratis->id, $outra->fresh()->plano_id);
    }

    public function test_trocar_para_o_mesmo_plano_e_rejeitado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $empresa = $contexto['empresa'];

        $resp = $this->post(route('assinatura.transicionar'), [
            'empresa_id' => $empresa->id,
            'plano_id' => $empresa->plano_id,
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('erro');
    }

    public function test_tela_oferece_a_renovacao_quando_o_teste_venceu(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $empresa = $contexto['empresa'];

        // Antes de vencer, a tela nao oferece renovacao.
        $empresa->update(['trial_expira_em' => now()->addDays(3)]);
        $this->get(route('assinatura.index'))
            ->assertOk()
            ->assertDontSee('Renovar teste');

        $empresa->update(['trial_expira_em' => now()->subDay()]);
        $this->artisan('assinaturas:expirar-trial')->assertSuccessful();

        $this->get(route('assinatura.index'))
            ->assertOk()
            ->assertSee('Renovar teste')
            ->assertSee('Teste encerrado');
    }

    public function test_admin_renova_o_teste_vencido_e_a_unidade_volta_ao_pro(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $empresa = $contexto['empresa'];
        $pro = Plano::where('slug', Plano::PRO)->firstOrFail();

        // Estado de quem teve o teste encerrado pelo comando agendado.
        $empresa->update(['trial_expira_em' => now()->subDay()]);
        $this->artisan('assinaturas:expirar-trial')->assertSuccessful();
        $this->assertTrue($empresa->refresh()->podeRenovarTrial());

        $resp = $this->post(route('assinatura.renovar-teste'), ['empresa_id' => $empresa->id]);

        $resp->assertRedirect(route('assinatura.index'));
        $resp->assertSessionHas('sucesso');

        $empresa->refresh();
        $this->assertSame($pro->id, $empresa->plano_id);
        $this->assertTrue($empresa->emTrial());
        $this->assertSame(Empresa::DIAS_DE_TRIAL, $empresa->diasRestantesTrial());
    }

    public function test_renovar_nao_gera_cobranca_na_fatura_do_mes(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $empresa = $contexto['empresa'];

        $empresa->update(['trial_expira_em' => now()->subDay()]);
        $this->artisan('assinaturas:expirar-trial')->assertSuccessful();

        $this->post(route('assinatura.renovar-teste'), ['empresa_id' => $empresa->id])->assertRedirect();

        // Unidade em teste nao e cobravel: a renovacao nao pode fabricar fatura.
        $this->assertDatabaseCount('faturas', 0);
    }

    public function test_renovar_e_repetivel_enquanto_nao_ha_gateway(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $empresa = $contexto['empresa'];

        foreach (range(1, 2) as $rodada) {
            $empresa->update(['trial_expira_em' => now()->subDay()]);
            $this->artisan('assinaturas:expirar-trial')->assertSuccessful();

            $this->post(route('assinatura.renovar-teste'), ['empresa_id' => $empresa->id])
                ->assertSessionHas('sucesso', fn (string $msg) => str_contains($msg, 'renovado'));

            $this->assertTrue($empresa->refresh()->emTrial(), "Rodada {$rodada} deveria reabrir o teste.");
        }
    }

    public function test_renovar_e_rejeitado_com_teste_ainda_ativo(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $empresa = $contexto['empresa'];
        $expiraEm = now()->addDays(5);
        $empresa->update(['trial_expira_em' => $expiraEm]);

        $resp = $this->post(route('assinatura.renovar-teste'), ['empresa_id' => $empresa->id]);

        $resp->assertRedirect();
        $resp->assertSessionHas('erro');
        $this->assertSame($expiraEm->toDateString(), $empresa->fresh()->trial_expira_em->toDateString());
    }

    public function test_renovar_e_rejeitado_em_unidade_que_nunca_teve_teste(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $outra = $this->criarEmpresaExtra($contexto['rede']->id, 'Filial Centro');
        session(['empresas_atuais' => [$contexto['empresa']->id, $outra->id]]);

        $resp = $this->post(route('assinatura.renovar-teste'), ['empresa_id' => $outra->id]);

        $resp->assertRedirect();
        $resp->assertSessionHas('erro');
        $this->assertNull($outra->fresh()->trial_expira_em);
    }

    public function test_renovar_e_rejeitado_em_licenca_paga(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $empresa = $contexto['empresa'];
        $pro = Plano::where('slug', Plano::PRO)->firstOrFail();

        // Guarda da Action, testada sem HTTP de proposito: por via web o middleware
        // rebaixaria a unidade antes, e "Pro com teste vencido" nunca chegaria aqui.
        $empresa->update(['trial_expira_em' => now()->subDay(), 'plano_id' => $pro->id]);

        $this->expectException(NegocioException::class);

        try {
            app(RenovarTrialAction::class)->executar($empresa);
        } finally {
            $this->assertFalse($empresa->fresh()->emTrial(), 'A licenca paga nao pode voltar ao teste.');
        }
    }

    public function test_usuario_sem_permissao_recebe_403_ao_renovar_o_teste(): void
    {
        $contexto = $this->criarRede();
        $empresa = $contexto['empresa'];
        $empresa->update(['trial_expira_em' => now()->subDay()]);
        $this->artisan('assinaturas:expirar-trial')->assertSuccessful();

        $comum = $this->criarUsuarioComum($contexto['rede'], $empresa, 'Recepcao');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($comum);
        session(['empresas_atuais' => [$empresa->id]]);

        $this->post(route('assinatura.renovar-teste'), ['empresa_id' => $empresa->id])
            ->assertForbidden();
        $this->assertFalse($empresa->fresh()->emTrial());
    }

    public function test_renovar_nao_alcanca_unidade_de_outra_rede(): void
    {
        $vizinha = $this->criarRede('Vizinha');
        $alvo = $vizinha['empresa'];
        $alvo->update(['trial_expira_em' => now()->subDay()]);
        $this->artisan('assinaturas:expirar-trial')->assertSuccessful();

        // Admin de OUTRA rede tenta renovar a licenca da vizinha.
        $this->criarRedeAutenticada();

        $resp = $this->post(route('assinatura.renovar-teste'), ['empresa_id' => $alvo->id]);

        $resp->assertRedirect();
        $resp->assertSessionHas('erro');
        $this->assertFalse($alvo->fresh()->emTrial());
    }

    public function test_usuario_sem_permissao_recebe_403_ao_transicionar(): void
    {
        $contexto = $this->criarRede();
        $comum = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Recepcao');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($comum);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $gratis = Plano::where('slug', Plano::GRATIS)->firstOrFail();

        $resp = $this->post(route('assinatura.transicionar'), [
            'empresa_id' => $contexto['empresa']->id,
            'plano_id' => $gratis->id,
        ]);

        $resp->assertForbidden();
        $this->assertSame($contexto['empresa']->plano_id, $contexto['empresa']->fresh()->plano_id);
    }
}
