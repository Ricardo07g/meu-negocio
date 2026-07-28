<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Modules\Tenant\Models\{Fatura, Plano};
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

    public function test_usuario_comum_ve_a_pagina_mas_sem_poder_trocar(): void
    {
        $contexto = $this->criarRede();
        $comum = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Recepcao');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($comum);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $resp = $this->get(route('assinatura.index'));

        $resp->assertOk();
        $resp->assertViewHas('podeTrocar', false);
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
