<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Actions;

use App\Exceptions\NegocioException;
use App\Modules\Tenant\Models\{Fatura, Plano, Rede};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TransicionarPlanoAction
{
    /**
     * Migra a rede para outro plano. Valida os limites do plano destino contra
     * o uso atual e ajusta a fatura do mes vigente de forma pro-rata (dias ja
     * decorridos no plano antigo + dias restantes no novo). Retorna a fatura
     * do mes ajustada. Tudo numa transacao.
     */
    public function executar(Rede $rede, Plano $destino): Fatura
    {
        /** @var Plano|null $atual */
        $atual = $rede->plano;

        if ($atual && $atual->id === $destino->id) {
            throw new NegocioException("A rede ja esta no plano \"{$destino->nome}\".");
        }

        $this->validarLimites($rede, $destino);

        return DB::transaction(function () use ($rede, $atual, $destino) {
            $rede->update(['plano_id' => $destino->id]);

            return $this->ajustarFaturaDoMes($rede, $atual, $destino);
        });
    }

    /**
     * Um downgrade nao pode deixar a rede acima dos limites do novo plano.
     * Limite 0 = ilimitado (nao restringe).
     */
    private function validarLimites(Rede $rede, Plano $destino): void
    {
        $usoEmpresas = $rede->empresas()->count();
        $usoUsuarios = $rede->usuarios()->count();

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
     * Como ha no maximo uma fatura por mes (unique rede_id+referencia), o ajuste
     * de plano no meio do mes recai sobre a fatura do mes vigente: cobra os dias
     * ja usados no preco antigo e os dias restantes no preco novo.
     */
    private function ajustarFaturaDoMes(Rede $rede, ?Plano $atual, Plano $destino): Fatura
    {
        $hoje = Carbon::now();
        $referencia = $hoje->format('Y-m');
        $diasNoMes = $hoje->daysInMonth;
        $diasUsados = $hoje->day - 1;                  // decorridos no plano antigo
        $diasRestantes = $diasNoMes - $diasUsados;     // inclui hoje, ja no plano novo

        $precoAntigo = $atual !== null ? (float) $atual->preco_mensal : 0.0;
        $precoNovo = (float) $destino->preco_mensal;

        $valorProRata = round(
            ($precoAntigo * $diasUsados + $precoNovo * $diasRestantes) / $diasNoMes,
            2
        );

        $fatura = Fatura::where('rede_id', $rede->id)
            ->where('referencia', $referencia)
            ->first();

        if ($fatura) {
            $fatura->update([
                'plano_id' => $destino->id,
                'valor' => $valorProRata,
            ]);

            return $fatura;
        }

        // Fallback raro: nao havia fatura do mes (a tela de assinatura a cria
        // ao abrir). Vence no fim do mes vigente.
        return Fatura::create([
            'rede_id' => $rede->id,
            'plano_id' => $destino->id,
            'referencia' => $referencia,
            'valor' => $valorProRata,
            'vencimento' => $hoje->copy()->endOfMonth(),
            'status' => 'em_aberto',
        ]);
    }
}
