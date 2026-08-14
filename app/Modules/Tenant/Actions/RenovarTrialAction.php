<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Actions;

use App\Exceptions\NegocioException;
use App\Modules\Tenant\Models\{Empresa, Plano};

/**
 * Reabre o teste gratuito de UMA unidade por mais `Empresa::DIAS_DE_RENOVACAO_TRIAL` dias.
 *
 * Enquanto nao ha gateway de pagamento, o fim do teste nao pode ser um beco sem saida: a
 * unidade cai no Gratis e, dali, o Admin escolhe entre contratar o Pro
 * (`TransicionarPlanoAction`) ou seguir avaliando com mais um periodo (esta Action).
 *
 * Nao ha limite de renovacoes de proposito — e uma cortesia temporaria, que sai quando a
 * cobranca de verdade entrar. Tambem nao mexe na fatura: a unidade sai do Gratis (R$ 0)
 * para o teste (nao cobravel), e nenhum dos dois entra no valor do mes.
 */
class RenovarTrialAction
{
    public function executar(Empresa $empresa): Empresa
    {
        $this->validarElegibilidade($empresa);

        $empresa->update([
            'plano_id' => Plano::where('slug', Plano::PRO)->firstOrFail()->id,
            'trial_expira_em' => now()->addDays(Empresa::DIAS_DE_RENOVACAO_TRIAL),
        ]);

        return $empresa;
    }

    /**
     * Renovar so faz sentido para quem ja testou, cujo teste acabou e que caiu no Gratis.
     */
    private function validarElegibilidade(Empresa $empresa): void
    {
        if ($empresa->trial_expira_em === null) {
            throw new NegocioException(
                "A unidade \"{$empresa->nome}\" nunca teve teste gratuito — ela foi contratada já no plano pago."
            );
        }

        if ($empresa->emTrial()) {
            throw new NegocioException(
                "O teste da unidade \"{$empresa->nome}\" ainda está ativo: faltam "
                ."{$empresa->diasRestantesTrial()} dia(s). Renove quando ele terminar."
            );
        }

        if ($empresa->plano->slug !== Plano::GRATIS) {
            throw new NegocioException(
                "A unidade \"{$empresa->nome}\" está no plano \"{$empresa->plano->nome}\", que é uma licença contratada. "
                .'O teste gratuito só pode ser reaberto a partir do plano Grátis.'
            );
        }
    }
}
