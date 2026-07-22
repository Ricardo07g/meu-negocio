<?php

declare(strict_types=1);

namespace App\Modules\Conta\Services;

use App\Enums\{FormatoExportacao, StatusExportacao};
use App\Modules\Conta\Jobs\GerarExportacaoExtrato;
use App\Modules\Conta\Models\{Conta, Exportacao};

class ExportacaoService
{
    /**
     * Registra um pedido de exportacao (status processando) e enfileira o job que
     * gera a planilha. Tenancy explicita: rede_id/empresa_id vem da propria conta
     * (os traits preenchem so-se-vazio, entao os valores explicitos sao respeitados).
     */
    public function solicitar(Conta $conta, FormatoExportacao $formato, string $de, string $ate, int $usuarioId): Exportacao
    {
        $exportacao = Exportacao::create([
            'rede_id' => $conta->rede_id,
            'empresa_id' => $conta->empresa_id,
            'conta_id' => $conta->id,
            'usuario_id' => $usuarioId,
            'formato' => $formato,
            'periodo_inicio' => $de,
            'periodo_fim' => $ate,
            'status' => StatusExportacao::Processando,
        ]);

        GerarExportacaoExtrato::dispatch($exportacao->id);

        return $exportacao;
    }
}
