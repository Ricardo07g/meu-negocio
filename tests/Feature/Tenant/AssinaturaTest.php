<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Enums\StatusFatura;
use App\Modules\Tenant\Models\{Fatura, Plano, Rede};
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

    /** Data fixa no meio de um mes de 31 dias -> pro-rata deterministico. */
    private function fixarData(): Carbon
    {
        $hoje = Carbon::create(2026, 1, 10, 12, 0, 0);
        Carbon::setTestNow($hoje);

        return $hoje;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function criarFatura(Rede $rede, Plano $plano, string $ref, float $valor, StatusFatura $status): Fatura
    {
        return Fatura::create([
            'rede_id' => $rede->id,
            'plano_id' => $plano->id,
            'referencia' => $ref,
            'valor' => $valor,
            'vencimento' => Carbon::createFromFormat('Y-m', $ref)->endOfMonth(),
            'pago_em' => $status === StatusFatura::Paga ? Carbon::now() : null,
            'status' => $status,
        ]);
    }

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

    public function test_upgrade_imediato_ajusta_fatura_em_aberto(): void
    {
        $hoje = $this->fixarData();
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['nome' => 'basico', 'preco_mensal' => 100, 'max_empresas' => 5, 'max_usuarios' => 10]);
        $destino = PlanoFactory::new()->create(['nome' => 'pro', 'preco_mensal' => 200, 'max_empresas' => 10, 'max_usuarios' => 20]);
        $rede->update(['plano_id' => $origem->id]);

        $fatura = $this->criarFatura($rede, $origem, $hoje->format('Y-m'), 100, StatusFatura::EmAberto);

        $resp = $this->post(route('assinatura.transicionar'), ['plano_id' => $destino->id]);

        $resp->assertRedirect(route('assinatura.index'));
        $resp->assertSessionHas('sucesso');

        $rede->refresh();
        $this->assertSame($destino->id, $rede->plano_id, 'Upgrade vale imediatamente.');
        $this->assertNull($rede->plano_agendado_id);

        $esperado = round((100 * 9 + 200 * 22) / 31, 2);
        $fatura->refresh();
        $this->assertSame($destino->id, $fatura->plano_id);
        $this->assertEqualsWithDelta($esperado, (float) $fatura->valor, 0.01);
        $this->assertSame(StatusFatura::EmAberto, $fatura->status);
    }

    public function test_upgrade_nao_sobrescreve_fatura_paga(): void
    {
        $hoje = $this->fixarData();
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['preco_mensal' => 100, 'max_empresas' => 5, 'max_usuarios' => 10]);
        $destino = PlanoFactory::new()->create(['preco_mensal' => 200, 'max_empresas' => 10, 'max_usuarios' => 20]);
        $rede->update(['plano_id' => $origem->id]);

        $fatura = $this->criarFatura($rede, $origem, $hoje->format('Y-m'), 100, StatusFatura::Paga);

        $this->post(route('assinatura.transicionar'), ['plano_id' => $destino->id])->assertRedirect();

        $rede->refresh();
        $this->assertSame($destino->id, $rede->plano_id, 'Upgrade vale imediatamente mesmo com fatura paga.');

        $fatura->refresh();
        $this->assertSame(StatusFatura::Paga, $fatura->status, 'Fatura paga nao pode ser tocada.');
        $this->assertSame($origem->id, $fatura->plano_id);
        $this->assertEqualsWithDelta(100, (float) $fatura->valor, 0.01);
    }

    public function test_upgrade_nao_sobrescreve_fatura_vencida(): void
    {
        $hoje = $this->fixarData();
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['preco_mensal' => 100, 'max_empresas' => 5, 'max_usuarios' => 10]);
        $destino = PlanoFactory::new()->create(['preco_mensal' => 200, 'max_empresas' => 10, 'max_usuarios' => 20]);
        $rede->update(['plano_id' => $origem->id]);

        $fatura = $this->criarFatura($rede, $origem, $hoje->format('Y-m'), 100, StatusFatura::Vencida);

        $this->post(route('assinatura.transicionar'), ['plano_id' => $destino->id])->assertRedirect();

        $rede->refresh();
        $this->assertSame($destino->id, $rede->plano_id);

        $fatura->refresh();
        $this->assertSame(StatusFatura::Vencida, $fatura->status, 'Fatura vencida nao e sobrescrita.');
        $this->assertEqualsWithDelta(100, (float) $fatura->valor, 0.01);
    }

    public function test_upgrade_sem_fatura_do_mes_cria_pro_rata(): void
    {
        $hoje = $this->fixarData();
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['preco_mensal' => 100, 'max_empresas' => 5, 'max_usuarios' => 10]);
        $destino = PlanoFactory::new()->create(['preco_mensal' => 200, 'max_empresas' => 10, 'max_usuarios' => 20]);
        $rede->update(['plano_id' => $origem->id]);

        $this->post(route('assinatura.transicionar'), ['plano_id' => $destino->id])->assertRedirect();

        $esperado = round((100 * 9 + 200 * 22) / 31, 2);
        $fatura = Fatura::where('rede_id', $rede->id)->where('referencia', $hoje->format('Y-m'))->first();
        $this->assertNotNull($fatura);
        $this->assertSame($destino->id, $fatura->plano_id);
        $this->assertEqualsWithDelta($esperado, (float) $fatura->valor, 0.01);
    }

    public function test_downgrade_agenda_e_nao_mexe_no_mes(): void
    {
        $hoje = $this->fixarData();
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['preco_mensal' => 200, 'max_empresas' => 0, 'max_usuarios' => 0]);
        $destino = PlanoFactory::new()->create(['nome' => 'menor', 'preco_mensal' => 100, 'max_empresas' => 0, 'max_usuarios' => 0]);
        $rede->update(['plano_id' => $origem->id]);

        $fatura = $this->criarFatura($rede, $origem, $hoje->format('Y-m'), 200, StatusFatura::EmAberto);

        $resp = $this->post(route('assinatura.transicionar'), ['plano_id' => $destino->id]);

        $resp->assertRedirect();
        $resp->assertSessionHas('sucesso');

        $rede->refresh();
        $this->assertSame($origem->id, $rede->plano_id, 'Downgrade NAO muda o plano agora.');
        $this->assertSame($destino->id, $rede->plano_agendado_id, 'Downgrade fica agendado.');

        $fatura->refresh();
        $this->assertSame($origem->id, $fatura->plano_id, 'Fatura do mes intacta no downgrade.');
        $this->assertEqualsWithDelta(200, (float) $fatura->valor, 0.01);
    }

    public function test_preco_igual_plano_diferente_e_imediato(): void
    {
        $this->fixarData();
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['preco_mensal' => 100, 'max_empresas' => 5, 'max_usuarios' => 10]);
        $destino = PlanoFactory::new()->create(['nome' => 'outro', 'preco_mensal' => 100, 'max_empresas' => 5, 'max_usuarios' => 10]);
        $rede->update(['plano_id' => $origem->id]);

        $this->post(route('assinatura.transicionar'), ['plano_id' => $destino->id])->assertRedirect();

        $rede->refresh();
        $this->assertSame($destino->id, $rede->plano_id, 'Preco igual: trata como imediato.');
        $this->assertNull($rede->plano_agendado_id);
    }

    public function test_downgrade_bloqueado_quando_excede_limite_de_empresas(): void
    {
        $this->fixarData();
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
        $rede->refresh();
        $this->assertSame($origem->id, $rede->plano_id, 'O plano nao deve mudar quando o limite e violado.');
        $this->assertNull($rede->plano_agendado_id, 'Nao deve agendar downgrade invalido.');
    }

    public function test_downgrade_bloqueado_quando_excede_limite_de_usuarios(): void
    {
        $this->fixarData();
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
        $rede->refresh();
        $this->assertSame($origem->id, $rede->plano_id);
        $this->assertNull($rede->plano_agendado_id);
    }

    public function test_trocar_para_o_mesmo_plano_sem_agendamento_e_rejeitado(): void
    {
        $this->fixarData();
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $planoAtual = Plano::find($rede->plano_id);

        $resp = $this->post(route('assinatura.transicionar'), ['plano_id' => $planoAtual->id]);

        $resp->assertRedirect();
        $resp->assertSessionHas('erro');
    }

    public function test_escolher_plano_atual_com_agendamento_cancela_o_downgrade(): void
    {
        $this->fixarData();
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['preco_mensal' => 200, 'max_empresas' => 0, 'max_usuarios' => 0]);
        $menor = PlanoFactory::new()->create(['nome' => 'menor', 'preco_mensal' => 100, 'max_empresas' => 0, 'max_usuarios' => 0]);
        $rede->update(['plano_id' => $origem->id, 'plano_agendado_id' => $menor->id]);

        $resp = $this->post(route('assinatura.transicionar'), ['plano_id' => $origem->id]);

        $resp->assertRedirect();
        $resp->assertSessionHas('sucesso');
        $rede->refresh();
        $this->assertSame($origem->id, $rede->plano_id);
        $this->assertNull($rede->plano_agendado_id, 'O downgrade agendado foi cancelado.');
    }

    public function test_plano_agendado_e_aplicado_na_virada_do_mes(): void
    {
        $this->fixarData(); // Jan/2026
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['preco_mensal' => 200, 'max_empresas' => 0, 'max_usuarios' => 0]);
        $destino = PlanoFactory::new()->create(['nome' => 'menor', 'preco_mensal' => 100, 'max_empresas' => 0, 'max_usuarios' => 0]);
        $rede->update(['plano_id' => $origem->id, 'plano_agendado_id' => $destino->id]);

        // Ultima fatura registrada e a de janeiro (mes do agendamento).
        $this->criarFatura($rede, $origem, '2026-01', 200, StatusFatura::Paga);

        // Vira o mes e abre a tela -> o agendado deve ser aplicado.
        Carbon::setTestNow(Carbon::create(2026, 2, 10, 12, 0, 0));
        $this->get(route('assinatura.index'))->assertOk();

        $rede->refresh();
        $this->assertSame($destino->id, $rede->plano_id, 'Plano agendado aplicado na virada.');
        $this->assertNull($rede->plano_agendado_id);

        $fev = Fatura::where('rede_id', $rede->id)->where('referencia', '2026-02')->first();
        $this->assertNotNull($fev, 'Fatura de fevereiro deve nascer no plano novo.');
        $this->assertSame($destino->id, $fev->plano_id);
        $this->assertEqualsWithDelta(100, (float) $fev->valor, 0.01);
    }

    public function test_agendado_inconsistente_na_virada_e_cancelado(): void
    {
        $this->fixarData(); // Jan/2026
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['preco_mensal' => 200, 'max_empresas' => 0, 'max_usuarios' => 0]);
        $destino = PlanoFactory::new()->create(['nome' => 'menor', 'preco_mensal' => 100, 'max_empresas' => 1, 'max_usuarios' => 0]);
        $rede->update(['plano_id' => $origem->id, 'plano_agendado_id' => $destino->id]);

        // Estoura o limite do destino DEPOIS de agendar (2 empresas > limite 1).
        EmpresaFactory::new()->create(['rede_id' => $rede->id]);
        $this->criarFatura($rede, $origem, '2026-01', 200, StatusFatura::Paga);

        Carbon::setTestNow(Carbon::create(2026, 2, 10, 12, 0, 0));
        $this->get(route('assinatura.index'))->assertOk();

        $rede->refresh();
        $this->assertNull($rede->plano_agendado_id, 'Agendamento inconsistente e cancelado.');
        $this->assertSame($origem->id, $rede->plano_id, 'Plano atual mantido quando o destino nao cabe mais.');
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

    public function test_admin_marca_fatura_em_aberto_como_paga(): void
    {
        $this->fixarData();
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $plano = Plano::find($rede->plano_id);
        $fatura = $this->criarFatura($rede, $plano, '2026-01', 100, StatusFatura::EmAberto);

        $resp = $this->post(route('assinatura.fatura.pagar', $fatura));

        $resp->assertRedirect(route('assinatura.index'));
        $resp->assertSessionHas('sucesso');
        $fatura->refresh();
        $this->assertSame(StatusFatura::Paga, $fatura->status);
        $this->assertNotNull($fatura->pago_em);
    }

    public function test_marcar_como_paga_falha_se_ja_paga(): void
    {
        $this->fixarData();
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $plano = Plano::find($rede->plano_id);
        $fatura = $this->criarFatura($rede, $plano, '2026-01', 100, StatusFatura::Paga);

        $resp = $this->post(route('assinatura.fatura.pagar', $fatura));

        $resp->assertRedirect();
        $resp->assertSessionHas('erro');
        $this->assertSame(StatusFatura::Paga, $fatura->fresh()->status);
    }

    public function test_usuario_sem_permissao_recebe_403_ao_pagar(): void
    {
        $this->fixarData();
        $contexto = $this->criarRede();
        $comum = $this->criarUsuarioComum($contexto['rede'], $contexto['empresa'], 'Recepcao');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($comum);
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        $plano = Plano::find($contexto['rede']->plano_id);
        $fatura = $this->criarFatura($contexto['rede'], $plano, '2026-01', 100, StatusFatura::EmAberto);

        $resp = $this->post(route('assinatura.fatura.pagar', $fatura));

        $resp->assertForbidden();
        $this->assertSame(StatusFatura::EmAberto, $fatura->fresh()->status);
    }

    public function test_previas_de_troca_sao_expostas_para_o_modal(): void
    {
        $this->fixarData();
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['preco_mensal' => 150, 'max_empresas' => 0, 'max_usuarios' => 0]);
        $maior = PlanoFactory::new()->create(['nome' => 'maior', 'preco_mensal' => 300, 'max_empresas' => 0, 'max_usuarios' => 0]);
        $menor = PlanoFactory::new()->create(['nome' => 'menor', 'preco_mensal' => 50, 'max_empresas' => 0, 'max_usuarios' => 0]);
        $rede->update(['plano_id' => $origem->id]);

        // Fatura do mes em aberto -> upgrade vira 'upgrade' (com ajuste pro-rata).
        $this->criarFatura($rede, $origem, '2026-01', 150, StatusFatura::EmAberto);

        $resp = $this->get(route('assinatura.index'));

        $resp->assertOk();
        $resp->assertViewHas('previas', function ($previas) use ($maior, $menor, $origem) {
            return ($previas[$maior->id]['tipo'] ?? null) === 'upgrade'
                && ($previas[$menor->id]['tipo'] ?? null) === 'downgrade'
                && ! array_key_exists($origem->id, $previas);
        });
    }

    public function test_downgrade_considera_apenas_usuarios_ativos(): void
    {
        $this->fixarData();
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['preco_mensal' => 200, 'max_empresas' => 0, 'max_usuarios' => 0]);
        $destino = PlanoFactory::new()->create(['nome' => 'menor', 'preco_mensal' => 100, 'max_empresas' => 0, 'max_usuarios' => 1]);
        $rede->update(['plano_id' => $origem->id]);

        // 1 admin ativo + 1 usuario INATIVO => 1 ativo cabe no destino (max_usuarios=1).
        $comum = $this->criarUsuarioComum($rede, $contexto['empresa'], 'Recepcao');
        $comum->update(['ativo' => false]);

        $resp = $this->post(route('assinatura.transicionar'), ['plano_id' => $destino->id]);

        $resp->assertRedirect();
        $resp->assertSessionHas('sucesso');
        $this->assertSame($destino->id, $rede->fresh()->plano_agendado_id, 'Inativos nao contam: downgrade agendado.');
    }

    public function test_previa_de_downgrade_bloqueado_quando_excede_limite(): void
    {
        $this->fixarData();
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['preco_mensal' => 200, 'max_empresas' => 0, 'max_usuarios' => 0]);
        $menor = PlanoFactory::new()->create(['nome' => 'menor', 'preco_mensal' => 50, 'max_empresas' => 1, 'max_usuarios' => 0]);
        $rede->update(['plano_id' => $origem->id]);

        // Rede ja tem 1 empresa; adiciona outra -> 2 > limite 1 do destino.
        EmpresaFactory::new()->create(['rede_id' => $rede->id]);

        $resp = $this->get(route('assinatura.index'));

        $resp->assertOk();
        $resp->assertViewHas('previas', function ($previas) use ($menor) {
            return ($previas[$menor->id]['tipo'] ?? null) === 'downgrade_bloqueado';
        });
    }

    public function test_banner_de_downgrade_agendado_aparece_na_tela(): void
    {
        $this->fixarData();
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $origem = PlanoFactory::new()->create(['preco_mensal' => 200, 'max_empresas' => 0, 'max_usuarios' => 0]);
        $menor = PlanoFactory::new()->create(['nome' => 'menor', 'preco_mensal' => 100, 'max_empresas' => 0, 'max_usuarios' => 0]);
        $rede->update(['plano_id' => $origem->id, 'plano_agendado_id' => $menor->id]);

        $resp = $this->get(route('assinatura.index'));

        $resp->assertOk();
        $resp->assertSee('Mudanca de plano agendada');
        $resp->assertSee('Cancelar mudanca agendada');
    }
}
