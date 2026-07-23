<?php

declare(strict_types=1);

namespace App\Modules\Caixa\Services;

use App\Modules\Caixa\Models\BaixaPagamento;
use Illuminate\Support\Collection;

/**
 * Panorama do dia por forma de pagamento (leitura). Regime "quando o cliente
 * pagou" (a baixa), NAO a liquidacao — recebido/estornado/liquido por forma.
 *
 * Fonte unica = BaixaPagamento (toda forma de recebimento passa por uma baixa,
 * com data + forma_pagamento_nome + valorTotal). Eixo DISJUNTO do saldo da gaveta
 * (que vem so de lancamentos por caixa_id) — estes numeros sao informativos e nao
 * entram no saldoCalculado do Caixa. Tenancy pela EmpresaTrait, como
 * DashboardService::receitaMes e CaixaService::caixaDoDia (a tela do Caixa Diario
 * ja resolve a empresa unica antes de chamar).
 */
class ResumoDiaService
{
    /**
     * @return array{
     *     linhas: list<array{forma: string, qtd: int, recebido: float, estornado: float, liquido: float}>,
     *     totalRecebido: float,
     *     totalEstornado: float,
     *     liquido: float
     * }
     */
    public function porForma(string $dia): array
    {
        return $this->porPeriodo($dia, $dia);
    }

    /**
     * Mesmo panorama por forma, num intervalo de datas [de, ate] (inclusive).
     *
     * @return array{
     *     linhas: list<array{forma: string, qtd: int, recebido: float, estornado: float, liquido: float}>,
     *     totalRecebido: float,
     *     totalEstornado: float,
     *     liquido: float
     * }
     */
    public function porPeriodo(string $de, string $ate): array
    {
        $recebidos = $this->recebimentosPorForma($de, $ate);
        $estornados = $this->estornosPorForma($de, $ate);

        $formas = $recebidos->keys()->merge($estornados->keys())->unique()->sort()->values();

        $linhas = $formas->map(function (string $forma) use ($recebidos, $estornados): array {
            $recebido = (float) ($recebidos->get($forma)['total'] ?? 0.0);
            $estornado = (float) $estornados->get($forma, 0.0);

            return [
                'forma' => $forma,
                'qtd' => (int) ($recebidos->get($forma)['qtd'] ?? 0),
                'recebido' => round($recebido, 2),
                'estornado' => round($estornado, 2),
                'liquido' => round($recebido - $estornado, 2),
            ];
        })->all();

        $totalRecebido = round((float) $recebidos->sum(fn (array $r): float => $r['total']), 2);
        $totalEstornado = round((float) $estornados->sum(), 2);

        return [
            'linhas' => $linhas,
            'totalRecebido' => $totalRecebido,
            'totalEstornado' => $totalEstornado,
            'liquido' => round($totalRecebido - $totalEstornado, 2),
        ];
    }

    /**
     * Lista as baixas do periodo (detalhe), pela data do pagamento, com a origem
     * (cliente) carregada para exibicao. Ordenadas da mais recente para a mais antiga.
     *
     * @return Collection<int, BaixaPagamento>
     */
    public function recebimentos(string $de, string $ate): Collection
    {
        return BaixaPagamento::query()
            ->whereBetween('data', [$de.' 00:00:00', $ate.' 23:59:59'])
            ->with('parcela.pagamento.cliente')
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Recebimentos por forma no intervalo: soma do bruto (valorTotal) das baixas
     * com data entre [de, ate].
     *
     * @return Collection<string, array{total: float, qtd: int}>
     */
    private function recebimentosPorForma(string $de, string $ate): Collection
    {
        return BaixaPagamento::query()
            ->whereBetween('data', [$de.' 00:00:00', $ate.' 23:59:59'])
            ->selectRaw('forma_pagamento_nome as forma')
            ->selectRaw('SUM(valor + multa + juros - desconto) as total')
            ->selectRaw('COUNT(*) as qtd')
            ->groupBy('forma_pagamento_nome')
            ->get()
            ->keyBy('forma')
            ->map(fn ($row): array => ['total' => (float) $row['total'], 'qtd' => (int) $row['qtd']]);
    }

    /**
     * Estornos por forma no intervalo: baixas marcadas como estornadas entre
     * [de, ate] (`estornado_em`), valuadas pelo bruto da propria baixa — neta
     * exato contra o recebido, sem residuo de taxa e sem depender de lancamento.
     *
     * @return Collection<string, float>
     */
    private function estornosPorForma(string $de, string $ate): Collection
    {
        return BaixaPagamento::query()
            ->whereBetween('estornado_em', [$de.' 00:00:00', $ate.' 23:59:59'])
            ->selectRaw('forma_pagamento_nome as forma')
            ->selectRaw('SUM(valor + multa + juros - desconto) as total')
            ->groupBy('forma_pagamento_nome')
            ->get()
            ->keyBy('forma')
            ->map(fn ($row): float => (float) $row['total']);
    }
}
