<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Support;

use Carbon\Carbon;

/**
 * Calculo pro-rata da fatura do mes vigente quando ha troca de plano no meio
 * do mes (ADR-0008): cobra os dias ja decorridos no preco antigo e os dias
 * restantes no preco novo. Fonte unica da formula — usada tanto pela
 * TransicionarPlanoAction (efeito real) quanto pelo AssinaturaController (previa).
 */
class CalculadoraProRata
{
    /**
     * valor = (preco_antigo * dias_decorridos + preco_novo * dias_restantes) / dias_no_mes
     *
     * dias_decorridos = dia_de_hoje - 1 (dias ja usados no plano antigo);
     * dias_restantes = dias_no_mes - dias_decorridos (inclui hoje, ja no plano novo).
     */
    public static function calcular(float $precoAntigo, float $precoNovo, ?Carbon $hoje = null): float
    {
        $hoje ??= Carbon::now();
        $diasNoMes = $hoje->daysInMonth;
        $diasUsados = $hoje->day - 1;
        $diasRestantes = $diasNoMes - $diasUsados;

        return round(($precoAntigo * $diasUsados + $precoNovo * $diasRestantes) / $diasNoMes, 2);
    }
}
