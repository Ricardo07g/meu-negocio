<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Actions;

use App\Modules\Tenant\Models\{Empresa, Plano};

/**
 * Encerra o teste gratuito das unidades vencidas, rebaixando-as para o Gratis.
 *
 * Idempotente e chamada de dois lugares: o comando agendado `assinaturas:expirar-trial`
 * (caminho normal) e, defensivamente, o middleware `VerificarRede` — para a conta nao
 * ficar presa no Pro se o scheduler estiver fora do ar.
 *
 * O rebaixamento acontece mesmo que a unidade tenha excedido os limites do Gratis
 * durante o teste: nada e apagado, apenas para de ser possivel criar mais.
 *
 * A data vencida NAO e apagada: ela e o registro de que a unidade ja teve teste, e e o
 * que habilita o Admin a renova-lo (`RenovarTrialAction`). Quem para a reexecucao e o
 * proprio plano de destino — uma unidade ja no Gratis sai da query.
 */
class EncerrarTrialAction
{
    /**
     * @param  Empresa|null  $empresa  restringe a uma unidade; null = todas as vencidas
     * @return int quantidade de licencas rebaixadas
     */
    public function executar(?Empresa $empresa = null): int
    {
        $planoGratis = Plano::where('slug', Plano::GRATIS)->first();

        if ($planoGratis === null) {
            return 0;
        }

        // `trial_expira_em` vale ate o fim do dia (espelha `Empresa::emTrial()`),
        // entao so vence quando a data ja ficou para tras.
        $query = Empresa::query()
            ->whereNotNull('trial_expira_em')
            ->whereDate('trial_expira_em', '<', now()->toDateString())
            ->where('plano_id', '!=', $planoGratis->id);

        if ($empresa !== null) {
            $query->whereKey($empresa->getKey());
        }

        return $query->update(['plano_id' => $planoGratis->id]);
    }
}
