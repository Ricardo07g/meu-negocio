<?php

declare(strict_types=1);

namespace App\Modules\Conta\Console;

use App\Modules\Conta\Models\Exportacao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Remove exportacoes de extrato expiradas: apaga o arquivo no storage E o registro.
 * Agendada de hora em hora (routes/console.php); retencao em Exportacao::DIAS_RETENCAO.
 * Sem auth/session no scheduler -> varre TODAS as redes/empresas (withoutGlobalScopes).
 */
class LimparExportacoes extends Command
{
    protected $signature = 'exportacoes:limpar';

    protected $description = 'Remove exportacoes de extrato expiradas (arquivo no storage + registro).';

    public function handle(): int
    {
        $expiradas = Exportacao::withoutGlobalScopes()
            ->where('created_at', '<', now()->subDays(Exportacao::DIAS_RETENCAO))
            ->get();

        $removidas = 0;
        foreach ($expiradas as $exportacao) {
            if ($exportacao->disco && $exportacao->caminho) {
                Storage::disk($exportacao->disco)->delete($exportacao->caminho);
            }
            $exportacao->delete();
            $removidas++;
        }

        $this->info("Exportacoes expiradas removidas: {$removidas}");

        return self::SUCCESS;
    }
}
