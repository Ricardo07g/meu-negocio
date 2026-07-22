<?php

declare(strict_types=1);

namespace App\Modules\Conta\Jobs;

use App\Enums\StatusExportacao;
use App\Modules\Conta\Exports\EscritorExtrato;
use App\Modules\Conta\Models\{Exportacao, Lancamento};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gera a planilha do extrato de uma conta num período e a grava no storage,
 * atualizando o status da Exportacao. Roda na fila (ADR-0012): não monta o
 * extrato grande de forma síncrona na request.
 *
 * Tenancy: o worker NÃO tem auth()/session(), então NÃO dependemos dos global
 * scopes (RedeTrait/EmpresaTrait). Recebemos só o id e resolvemos tudo
 * explicitamente com `withoutGlobalScopes()` + where rede_id/empresa_id/conta_id
 * (padrão do CaixaService; ver ADR-0004).
 */
class GerarExportacaoExtrato implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $exportacaoId) {}

    public function handle(): void
    {
        // Pode ter sido excluida manualmente antes do worker pegar: sai sem erro.
        $exportacao = Exportacao::withoutGlobalScopes()->find($this->exportacaoId);
        if ($exportacao === null) {
            return;
        }

        $query = Lancamento::query()
            ->withoutGlobalScopes()
            ->where('rede_id', $exportacao->rede_id)
            ->where('empresa_id', $exportacao->empresa_id)
            ->where('conta_id', $exportacao->conta_id)
            ->whereBetween('data', [
                $exportacao->periodo_inicio->toDateString(),
                $exportacao->periodo_fim->toDateString(),
            ]);

        $disco = (string) config('arquivos.disco', 'r2');
        $ext = $exportacao->formato->extensao();
        $nomeArquivo = sprintf(
            'extrato-%s-a-%s.%s',
            $exportacao->periodo_inicio->format('Y-m-d'),
            $exportacao->periodo_fim->format('Y-m-d'),
            $ext,
        );
        $caminho = sprintf(
            'sistema/redes/%d/empresas/%d/exportacoes/%s.%s',
            $exportacao->rede_id,
            $exportacao->empresa_id,
            Str::uuid()->toString(),
            $ext,
        );

        $tmp = sys_get_temp_dir().'/'.Str::uuid()->toString().'.'.$ext;

        try {
            (new EscritorExtrato($exportacao->formato))->escrever($query, $tmp);

            Storage::disk($disco)->put($caminho, (string) file_get_contents($tmp));

            $exportacao->update([
                'status' => StatusExportacao::Pronto,
                'disco' => $disco,
                'caminho' => $caminho,
                'nome_arquivo' => $nomeArquivo,
                'tamanho' => filesize($tmp) ?: null,
            ]);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Exportacao::withoutGlobalScopes()->find($this->exportacaoId)?->update([
            'status' => StatusExportacao::Erro,
            'erro' => Str::limit($e->getMessage(), 500),
        ]);
    }
}
