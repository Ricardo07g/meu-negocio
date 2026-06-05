<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Modules\Tenant\Models\{Fatura, Plano};
use Carbon\Carbon;
use Database\Factories\{EmpresaFactory, PlanoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

class AssinaturaTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    public function test_admin_ve_pagina_de_assinatura_e_gera_fatura_do_mes(): void
    {
        $this->criarRedeAutenticada();

        $resp = $this->get(route('assinatura.index'));

        $resp->assertOk();
        $resp->assertViewIs('tenant::assinatura');
        $this->assertDatabaseHas('faturas', ['referencia' => Carbon::now()->format('Y-m')]);
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

    public function test_admin_troca_de_plano_e_ajusta_fatura_pro_rata(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['nome' => 'basico', 'preco_mensal' => 100, 'max_empresas' => 5, 'max_usuarios' => 10]);
        $destino = PlanoFactory::new()->create(['nome' => 'pro', 'preco_mensal' => 200, 'max_empresas' => 10, 'max_usuarios' => 20]);
        $rede->update(['plano_id' => $origem->id]);

        $resp = $this->post(route('assinatura.transicionar'), ['plano_id' => $destino->id]);

        $resp->assertRedirect(route('assinatura.index'));
        $resp->assertSessionHas('sucesso');
        $this->assertSame($destino->id, $rede->fresh()->plano_id);

        $hoje = Carbon::now();
        $dias = $hoje->daysInMonth;
        $usados = $hoje->day - 1;
        $restantes = $dias - $usados;
        $esperado = round((100 * $usados + 200 * $restantes) / $dias, 2);

        $fatura = Fatura::where('rede_id', $rede->id)->where('referencia', $hoje->format('Y-m'))->first();
        $this->assertNotNull($fatura);
        $this->assertSame($destino->id, $fatura->plano_id);
        $this->assertEqualsWithDelta($esperado, (float) $fatura->valor, 0.01);
    }

    public function test_troca_ajusta_a_fatura_em_aberto_ja_existente(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['preco_mensal' => 100, 'max_empresas' => 5, 'max_usuarios' => 10]);
        $destino = PlanoFactory::new()->create(['preco_mensal' => 300, 'max_empresas' => 10, 'max_usuarios' => 20]);
        $rede->update(['plano_id' => $origem->id]);

        $ref = Carbon::now()->format('Y-m');
        $faturaExistente = Fatura::create([
            'rede_id' => $rede->id,
            'plano_id' => $origem->id,
            'referencia' => $ref,
            'valor' => 100,
            'vencimento' => Carbon::now()->endOfMonth(),
            'status' => 'em_aberto',
        ]);

        $this->post(route('assinatura.transicionar'), ['plano_id' => $destino->id])->assertRedirect();

        // Mesma fatura (sem duplicar — unique rede+referencia), agora no plano novo.
        $this->assertSame(1, Fatura::where('rede_id', $rede->id)->where('referencia', $ref)->count());
        $atualizada = $faturaExistente->fresh();
        $this->assertSame($destino->id, $atualizada->plano_id);
        $this->assertGreaterThan(100, (float) $atualizada->valor);
    }

    public function test_downgrade_bloqueado_quando_excede_limite_de_empresas(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['preco_mensal' => 200, 'max_empresas' => 5, 'max_usuarios' => 10]);
        $destino = PlanoFactory::new()->create(['preco_mensal' => 50, 'max_empresas' => 1, 'max_usuarios' => 10]);
        $rede->update(['plano_id' => $origem->id]);

        // Rede ja tem 1 empresa; adiciona outra -> 2 > limite 1 do destino.
        EmpresaFactory::new()->create(['rede_id' => $rede->id]);

        $resp = $this->post(route('assinatura.transicionar'), ['plano_id' => $destino->id]);

        $resp->assertRedirect();
        $resp->assertSessionHas('erro');
        $this->assertSame($origem->id, $rede->fresh()->plano_id, 'O plano nao deve mudar quando o limite e violado.');
    }

    public function test_downgrade_bloqueado_quando_excede_limite_de_usuarios(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['preco_mensal' => 200, 'max_empresas' => 10, 'max_usuarios' => 10]);
        $destino = PlanoFactory::new()->create(['preco_mensal' => 50, 'max_empresas' => 10, 'max_usuarios' => 1]);
        $rede->update(['plano_id' => $origem->id]);

        // Rede ja tem 1 usuario (admin); adiciona outro -> 2 > limite 1 do destino.
        $this->criarUsuarioComum($rede, $contexto['empresa'], 'Recepcao');

        $resp = $this->post(route('assinatura.transicionar'), ['plano_id' => $destino->id]);

        $resp->assertRedirect();
        $resp->assertSessionHas('erro');
        $this->assertSame($origem->id, $rede->fresh()->plano_id);
    }

    public function test_trocar_para_o_mesmo_plano_e_rejeitado(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $planoAtual = Plano::find($rede->plano_id);

        $resp = $this->post(route('assinatura.transicionar'), ['plano_id' => $planoAtual->id]);

        $resp->assertRedirect();
        $resp->assertSessionHas('erro');
    }

    public function test_usuario_sem_permissao_recebe_403_ao_transicionar(): void
    {
        $contexto = $this->criarRede();
        $destino = PlanoFactory::new()->create(['max_empresas' => 10, 'max_usuarios' => 10]);
        $comum = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Recepcao');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($comum);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $resp = $this->post(route('assinatura.transicionar'), ['plano_id' => $destino->id]);

        $resp->assertForbidden();
        $this->assertSame($contexto['rede']->plano_id, $contexto['rede']->fresh()->plano_id);
    }
}
