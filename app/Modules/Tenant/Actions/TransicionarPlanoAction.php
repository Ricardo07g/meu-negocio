<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Actions;

use App\Enums\StatusFatura;
use App\Exceptions\NegocioException;
use App\Modules\Tenant\DTOs\ResultadoTransicao;
use App\Modules\Tenant\Models\{Fatura, Plano, Rede};
use App\Modules\Tenant\Support\CalculadoraProRata;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TransicionarPlanoAction
{
    /**
     * Migra a rede para outro plano (ADR-0008). Distingue upgrade de downgrade
     * pelo preco mensal:
     *  - UPGRADE (ou preco igual): efeito imediato — troca o plano agora e ajusta
     *    a fatura do mes pro-rata SOMENTE se ela ainda estiver em aberto (fatura
     *    paga/vencida nao e tocada);
     *  - DOWNGRADE: agenda a troca para a virada do mes (plano_agendado_id), sem
     *    mexer no plano atual nem na fatura — sem reembolso, mantem recursos ate la.
     * Escolher de novo o plano atual quando ha um downgrade agendado cancela o
     * agendamento. Tudo em transacao.
     */
    public function executar(Rede $rede, Plano $destino): ResultadoTransicao
    {
        /** @var Plano|null $atual */
        $atual = $rede->plano;

        if ($atual && $atual->id === $destino->id) {
            if ($rede->plano_agendado_id !== null) {
                $rede->update(['plano_agendado_id' => null]);

                return ResultadoTransicao::agendamentoCancelado($destino);
            }

            throw new NegocioException("A rede ja esta no plano \"{$destino->nome}\".");
        }

        $this->validarLimites($rede, $destino);

        $precoAtual = $atual !== null ? (float) $atual->preco_mensal : 0.0;
        $precoNovo = (float) $destino->preco_mensal;

        if ($precoNovo < $precoAtual) {
            // Downgrade: so vale no proximo ciclo.
            $rede->update(['plano_agendado_id' => $destino->id]);
            $vigencia = Carbon::now()->addMonthNoOverflow()->startOfMonth();

            return ResultadoTransicao::downgradeAgendado($destino, $vigencia);
        }

        // Upgrade (ou preco igual com plano diferente): efeito imediato.
        return DB::transaction(function () use ($rede, $atual, $destino) {
            $rede->update(['plano_id' => $destino->id, 'plano_agendado_id' => null]);
            $fatura = $this->ajustarFaturaDoMesSeAberta($rede, $atual, $destino);

            return $fatura !== null
                ? ResultadoTransicao::upgradeAjustado($destino, $fatura)
                : ResultadoTransicao::upgradeSemAjuste($destino);
        });
    }

    /**
     * Um downgrade nao pode deixar a rede acima dos limites do novo plano.
     * Limite 0 = ilimitado (nao restringe).
     */
    private function validarLimites(Rede $rede, Plano $destino): void
    {
        $usoEmpresas = $rede->empresas()->count();
        $usoUsuarios = $rede->usuariosAtivos()->count();

        if ($destino->max_empresas > 0 && $usoEmpresas > $destino->max_empresas) {
            throw new NegocioException(
                "O plano \"{$destino->nome}\" permite {$destino->max_empresas} empresa(s), "
                ."mas a rede possui {$usoEmpresas}. Reduza o numero de empresas antes de migrar."
            );
        }

        if ($destino->max_usuarios > 0 && $usoUsuarios > $destino->max_usuarios) {
            throw new NegocioException(
                "O plano \"{$destino->nome}\" permite {$destino->max_usuarios} usuario(s), "
                ."mas a rede possui {$usoUsuarios}. Reduza o numero de usuarios antes de migrar."
            );
        }
    }

    /**
     * Ajusta a fatura do mes vigente pro-rata, mas SOMENTE se ela existir e
     * estiver em aberto — fatura paga/vencida nao pode ser sobrescrita (retorna
     * null nesse caso, o upgrade vale a partir da proxima fatura). Se nao houver
     * fatura do mes (a tela costuma cria-la ao abrir), cria ja no plano novo.
     */
    private function ajustarFaturaDoMesSeAberta(Rede $rede, ?Plano $atual, Plano $destino): ?Fatura
    {
        $referencia = Carbon::now()->format('Y-m');
        $valorProRata = CalculadoraProRata::calcular(
            $atual !== null ? (float) $atual->preco_mensal : 0.0,
            (float) $destino->preco_mensal,
        );

        $fatura = Fatura::where('rede_id', $rede->id)
            ->where('referencia', $referencia)
            ->first();

        if ($fatura !== null) {
            if ($fatura->status !== StatusFatura::EmAberto) {
                return null;
            }

            $fatura->update([
                'plano_id' => $destino->id,
                'valor' => $valorProRata,
            ]);

            return $fatura;
        }

        return Fatura::create([
            'rede_id' => $rede->id,
            'plano_id' => $destino->id,
            'referencia' => $referencia,
            'valor' => $valorProRata,
            'vencimento' => Carbon::now()->endOfMonth(),
            'status' => StatusFatura::EmAberto,
        ]);
    }
}
