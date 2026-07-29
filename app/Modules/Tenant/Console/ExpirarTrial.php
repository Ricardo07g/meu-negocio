<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Console;

use App\Modules\Tenant\Actions\EncerrarTrialAction;
use Illuminate\Console\Command;

/**
 * Rebaixa para o Gratis as unidades cujo teste gratuito venceu.
 * Agendada diariamente (routes/console.php); prazo em Empresa::DIAS_DE_TRIAL.
 * Sem auth no scheduler -> o global scope de rede no-opa e a varredura pega todas as redes.
 */
class ExpirarTrial extends Command
{
    protected $signature = 'assinaturas:expirar-trial';

    protected $description = 'Encerra os testes gratuitos vencidos, rebaixando as unidades para o plano Gratis.';

    public function handle(EncerrarTrialAction $encerrarTrial): int
    {
        $rebaixadas = $encerrarTrial->executar();

        $this->info("Licencas rebaixadas para o Gratis: {$rebaixadas}");

        return self::SUCCESS;
    }
}
