<?php

declare(strict_types=1);

namespace App\Modules\Arquivo\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Remove arquivos de staging (tmp) abandonados, mais antigos que o TTL.
 *
 * E um reforco: o ideal e configurar tambem uma regra de lifecycle no bucket
 * R2 sobre o prefixo "{pasta_sistema}/{prefixo_tmp}/".
 */
class LimparRascunhosArquivo extends Command
{
    protected $signature = 'arquivos:limpar-rascunhos';

    protected $description = 'Remove arquivos de staging (tmp) mais antigos que o TTL configurado.';

    public function handle(): int
    {
        $disco = Storage::disk((string) config('arquivos.disco'));
        $dir = config('arquivos.pasta_sistema').'/'.config('arquivos.prefixo_tmp');
        $limite = now()->subHours((int) config('arquivos.tmp_ttl_horas'))->getTimestamp();

        $removidos = 0;
        foreach ($disco->allFiles($dir) as $arquivo) {
            if ($disco->lastModified($arquivo) < $limite) {
                $disco->delete($arquivo);
                $removidos++;
            }
        }

        $this->info("Rascunhos de arquivo removidos: {$removidos}");

        return self::SUCCESS;
    }
}
