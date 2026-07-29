<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Actions;

use App\Exceptions\NegocioException;
use App\Modules\Tenant\Models\{Empresa, Fatura, Plano};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Troca a licenca de UMA unidade e ajusta a fatura da rede no mes vigente.
 *
 * Upgrade Gratis -> Pro e self-service do Admin; downgrade e contratacao de unidade
 * nova sao do operador (o painel de superusuario chamara esta mesma Action).
 *
 * O rateio por dias aqui e transitorio: na Fase 2 a fatura ganha itens (um por unidade)
 * e o calculo passa a ser por dias reais de cada licenca, aposentando esta aproximacao.
 */
class TransicionarPlanoAction
{
    public function executar(Empresa $empresa, Plano $destino): Fatura
    {
        $atual = $empresa->plano;

        if ($atual->id === $destino->id) {
            throw new NegocioException("A unidade \"{$empresa->nome}\" ja esta no plano \"{$destino->nome}\".");
        }

        $this->validarLimites($empresa, $destino);
        $this->validarLicencaGratis($empresa, $destino);

        return DB::transaction(function () use ($empresa, $atual, $destino) {
            // Contratar encerra o teste gratuito: a partir daqui a licenca e paga.
            $empresa->update(['plano_id' => $destino->id, 'trial_expira_em' => null]);

            return $this->ajustarFaturaDoMes($empresa, $atual, $destino);
        });
    }

    /** Um downgrade nao pode deixar a unidade acima dos assentos do plano destino. */
    private function validarLimites(Empresa $empresa, Plano $destino): void
    {
        $usoUsuarios = $empresa->contarUsuarios();

        if ($usoUsuarios > $destino->max_usuarios) {
            throw new NegocioException(
                "O plano \"{$destino->nome}\" permite {$destino->max_usuarios} usuario(s), "
                ."mas a unidade possui {$usoUsuarios}. Reduza o numero de usuarios antes de migrar."
            );
        }
    }

    /** O Gratis vale para uma unica unidade por rede. */
    private function validarLicencaGratis(Empresa $empresa, Plano $destino): void
    {
        if ($destino->slug !== Plano::GRATIS) {
            return;
        }

        $outraGratis = $empresa->rede->empresas()
            ->where('plano_id', $destino->id)
            ->whereKeyNot($empresa->getKey())
            ->exists();

        if ($outraGratis) {
            throw new NegocioException(
                'A rede ja possui uma unidade no plano Grátis — ele vale para uma única unidade.'
            );
        }
    }

    /**
     * Ha no maximo uma fatura por mes por rede (unique rede_id+referencia), entao a troca
     * recai sobre ela: soma as demais licencas cobraveis e rateia por dias so a que mudou.
     */
    private function ajustarFaturaDoMes(Empresa $empresa, Plano $atual, Plano $destino): Fatura
    {
        $hoje = Carbon::now();
        $referencia = $hoje->format('Y-m');
        $diasNoMes = $hoje->daysInMonth;
        $diasUsados = $hoje->day - 1;                  // decorridos no plano antigo
        $diasRestantes = $diasNoMes - $diasUsados;     // inclui hoje, ja no plano novo

        $rateioDaUnidade = (
            (float) $atual->preco_por_licenca * $diasUsados
            + (float) $destino->preco_por_licenca * $diasRestantes
        ) / $diasNoMes;

        $valor = round($this->valorDasDemaisLicencas($empresa) + $rateioDaUnidade, 2);

        $fatura = Fatura::where('rede_id', $empresa->rede_id)
            ->where('referencia', $referencia)
            ->first();

        if ($fatura) {
            $fatura->update(['plano_id' => $destino->id, 'valor' => $valor]);

            return $fatura;
        }

        return Fatura::create([
            'rede_id' => $empresa->rede_id,
            'plano_id' => $destino->id,
            'referencia' => $referencia,
            'valor' => $valor,
            'vencimento' => $hoje->copy()->endOfMonth(),
            'status' => 'em_aberto',
        ]);
    }

    /** Demais unidades da rede, ja cobraveis (fora do teste gratuito). */
    private function valorDasDemaisLicencas(Empresa $empresa): float
    {
        return (float) $empresa->rede->empresas()
            ->with('plano')
            ->whereKeyNot($empresa->getKey())
            ->get()
            ->reject(fn (Empresa $outra) => $outra->emTrial())
            ->sum(fn (Empresa $outra) => (float) $outra->plano->preco_por_licenca);
    }
}
