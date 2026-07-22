<?php

declare(strict_types=1);

namespace Tests\Feature\Conta;

use App\Enums\{FormatoExportacao, StatusExportacao};
use App\Modules\Conta\Jobs\GerarExportacaoExtrato;
use App\Modules\Conta\Models\{Conta, Exportacao};
use Database\Factories\LancamentoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, Storage};
use Tests\TestCase;

/**
 * Exportacao do extrato por periodo (planilha) via job (ADR-0012): a request so
 * enfileira; o job gera o arquivo, grava no storage e marca o status; o download
 * e autenticado e isolado por conta/empresa.
 */
class ExportacaoExtratoTest extends TestCase
{
    use RefreshDatabase;

    private function contaGaveta(int $empresaId): Conta
    {
        return Conta::where('empresa_id', $empresaId)->where('eh_caixa_padrao', true)->firstOrFail();
    }

    public function test_exportar_cria_pedido_processando_e_enfileira_job(): void
    {
        Bus::fake();
        $ctx = $this->criarRedeAutenticada();
        $conta = $this->contaGaveta($ctx['empresa']->id);

        $this->post(route('contas.exportar', $conta), [
            'de' => '2026-07-01',
            'ate' => '2026-07-31',
            'formato' => 'csv',
        ])->assertRedirect();

        $exportacao = Exportacao::firstOrFail();
        $this->assertSame(StatusExportacao::Processando, $exportacao->status);
        $this->assertSame($conta->id, $exportacao->conta_id);
        $this->assertSame(FormatoExportacao::Csv, $exportacao->formato);
        $this->assertSame($ctx['empresa']->id, $exportacao->empresa_id, 'Exportação nasce na empresa da conta.');

        Bus::assertDispatched(GerarExportacaoExtrato::class);
    }

    public function test_periodo_final_antes_do_inicial_e_rejeitado(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $conta = $this->contaGaveta($ctx['empresa']->id);

        $this->post(route('contas.exportar', $conta), [
            'de' => '2026-07-31',
            'ate' => '2026-07-01',
            'formato' => 'csv',
        ])->assertSessionHasErrors('ate');

        $this->assertSame(0, Exportacao::count());
    }

    public function test_job_gera_planilha_com_lancamentos_do_periodo_e_marca_pronto(): void
    {
        Storage::fake('r2');
        config(['arquivos.disco' => 'r2']);
        $ctx = $this->criarRedeAutenticada();
        $conta = $this->contaGaveta($ctx['empresa']->id);

        LancamentoFactory::new()->credito()->create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'conta_id' => $conta->id,
            'valor' => 150.00,
            'data' => '2026-07-10',
            'descricao' => 'Recebimento dentro do periodo',
        ]);
        LancamentoFactory::new()->debito()->create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'conta_id' => $conta->id,
            'valor' => 999.00,
            'data' => '2026-08-05',
            'descricao' => 'Fora do periodo',
        ]);

        $exportacao = $this->criarExportacao($ctx, $conta->id, StatusExportacao::Processando);

        (new GerarExportacaoExtrato($exportacao->id))->handle();

        $exportacao->refresh();
        $this->assertSame(StatusExportacao::Pronto, $exportacao->status);
        $this->assertNotNull($exportacao->caminho);
        $this->assertGreaterThan(0, (int) $exportacao->tamanho);
        Storage::disk('r2')->assertExists($exportacao->caminho);

        $conteudo = Storage::disk('r2')->get($exportacao->caminho);
        $this->assertStringContainsString('Recebimento dentro do periodo', $conteudo);
        $this->assertStringNotContainsString('Fora do periodo', $conteudo, 'Lançamento fora do período não entra.');
    }

    public function test_job_gera_xlsx_valido(): void
    {
        Storage::fake('r2');
        config(['arquivos.disco' => 'r2']);
        $ctx = $this->criarRedeAutenticada();
        $conta = $this->contaGaveta($ctx['empresa']->id);

        LancamentoFactory::new()->credito()->create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'conta_id' => $conta->id,
            'valor' => 50.00,
            'data' => '2026-07-05',
            'descricao' => 'Item XLSX',
        ]);

        $exportacao = $this->criarExportacao($ctx, $conta->id, StatusExportacao::Processando, formato: FormatoExportacao::Xlsx);

        (new GerarExportacaoExtrato($exportacao->id))->handle();

        $exportacao->refresh();
        $this->assertSame(StatusExportacao::Pronto, $exportacao->status);
        Storage::disk('r2')->assertExists($exportacao->caminho);
        $this->assertStringStartsWith('PK', Storage::disk('r2')->get($exportacao->caminho), 'XLSX é um zip (assinatura PK).');
    }

    public function test_baixar_exportacao_pronta_faz_download_autenticado(): void
    {
        Storage::fake('r2');
        $ctx = $this->criarRedeAutenticada();
        $conta = $this->contaGaveta($ctx['empresa']->id);

        Storage::disk('r2')->put('exportacoes/teste.csv', "Data;Tipo\n");
        $exportacao = $this->criarExportacao($ctx, $conta->id, StatusExportacao::Pronto, 'exportacoes/teste.csv');

        $this->get(route('contas.exportacoes.baixar', ['conta' => $conta, 'exportacao' => $exportacao]))
            ->assertOk()
            ->assertDownload('extrato.csv');
    }

    public function test_baixar_exportacao_de_outra_conta_da_404(): void
    {
        Storage::fake('r2');
        $ctx = $this->criarRedeAutenticada();
        $conta = $this->contaGaveta($ctx['empresa']->id);
        $bancoId = (int) Conta::where('empresa_id', $ctx['empresa']->id)
            ->where('eh_destino_recebivel_padrao', true)->value('id');

        Storage::disk('r2')->put('exportacoes/x.csv', 'x');
        // Exportacao e de OUTRA conta (banco), mas a URL usa a conta gaveta.
        $exportacao = $this->criarExportacao($ctx, $bancoId, StatusExportacao::Pronto, 'exportacoes/x.csv');

        $this->get(route('contas.exportacoes.baixar', ['conta' => $conta, 'exportacao' => $exportacao]))
            ->assertNotFound();
    }

    public function test_status_endpoint_retorna_json_das_exportacoes(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $conta = $this->contaGaveta($ctx['empresa']->id);
        $this->criarExportacao($ctx, $conta->id, StatusExportacao::Processando);

        $this->getJson(route('contas.exportacoes.status', $conta))
            ->assertOk()
            ->assertJson(['processando' => true])
            ->assertJsonStructure(['processando', 'exportacoes' => [['status', 'expiraEm', 'podeExcluir', 'urlExcluir']]])
            ->assertJsonPath('exportacoes.0.status', 'processando')
            ->assertJsonPath('exportacoes.0.urlDownload', null)
            ->assertJsonPath('exportacoes.0.podeExcluir', false);
    }

    public function test_comando_limpa_expiradas_e_mantem_recentes(): void
    {
        Storage::fake('r2');
        $ctx = $this->criarRedeAutenticada();
        $conta = $this->contaGaveta($ctx['empresa']->id);

        Storage::disk('r2')->put('exportacoes/velha.csv', 'x');
        $velha = $this->criarExportacao($ctx, $conta->id, StatusExportacao::Pronto, 'exportacoes/velha.csv');
        // Retencao = 1 dia; created_at ha 2 dias -> expirada.
        Exportacao::where('id', $velha->id)->update(['created_at' => now()->subDays(2)]);

        Storage::disk('r2')->put('exportacoes/nova.csv', 'y');
        $nova = $this->criarExportacao($ctx, $conta->id, StatusExportacao::Pronto, 'exportacoes/nova.csv');

        $this->artisan('exportacoes:limpar')->assertExitCode(0);

        $this->assertDatabaseMissing('exportacoes', ['id' => $velha->id]);
        Storage::disk('r2')->assertMissing('exportacoes/velha.csv');
        $this->assertDatabaseHas('exportacoes', ['id' => $nova->id]);
        Storage::disk('r2')->assertExists('exportacoes/nova.csv');
    }

    public function test_excluir_manual_remove_arquivo_e_registro(): void
    {
        Storage::fake('r2');
        $ctx = $this->criarRedeAutenticada();
        $conta = $this->contaGaveta($ctx['empresa']->id);

        Storage::disk('r2')->put('exportacoes/del.csv', 'x');
        $exportacao = $this->criarExportacao($ctx, $conta->id, StatusExportacao::Pronto, 'exportacoes/del.csv');

        $this->delete(route('contas.exportacoes.excluir', ['conta' => $conta, 'exportacao' => $exportacao]))
            ->assertRedirect();

        $this->assertDatabaseMissing('exportacoes', ['id' => $exportacao->id]);
        Storage::disk('r2')->assertMissing('exportacoes/del.csv');
    }

    public function test_excluir_exportacao_de_outra_conta_da_404(): void
    {
        Storage::fake('r2');
        $ctx = $this->criarRedeAutenticada();
        $conta = $this->contaGaveta($ctx['empresa']->id);
        $bancoId = (int) Conta::where('empresa_id', $ctx['empresa']->id)
            ->where('eh_destino_recebivel_padrao', true)->value('id');

        Storage::disk('r2')->put('exportacoes/b.csv', 'x');
        $exportacao = $this->criarExportacao($ctx, $bancoId, StatusExportacao::Pronto, 'exportacoes/b.csv');

        $this->delete(route('contas.exportacoes.excluir', ['conta' => $conta, 'exportacao' => $exportacao]))
            ->assertNotFound();
        $this->assertDatabaseHas('exportacoes', ['id' => $exportacao->id]);
    }

    public function test_baixar_exportacao_ainda_processando_da_404(): void
    {
        $ctx = $this->criarRedeAutenticada();
        $conta = $this->contaGaveta($ctx['empresa']->id);
        $exportacao = $this->criarExportacao($ctx, $conta->id, StatusExportacao::Processando);

        $this->get(route('contas.exportacoes.baixar', ['conta' => $conta, 'exportacao' => $exportacao]))
            ->assertNotFound();
    }

    /** @param array{rede: object, empresa: object, usuario: object} $ctx */
    private function criarExportacao(
        array $ctx,
        int $contaId,
        StatusExportacao $status,
        ?string $caminho = null,
        FormatoExportacao $formato = FormatoExportacao::Csv,
    ): Exportacao {
        return Exportacao::create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'conta_id' => $contaId,
            'usuario_id' => $ctx['usuario']->id,
            'formato' => $formato,
            'periodo_inicio' => '2026-07-01',
            'periodo_fim' => '2026-07-31',
            'status' => $status,
            'disco' => $caminho ? 'r2' : null,
            'caminho' => $caminho,
            'nome_arquivo' => $caminho ? 'extrato.csv' : null,
        ]);
    }
}
