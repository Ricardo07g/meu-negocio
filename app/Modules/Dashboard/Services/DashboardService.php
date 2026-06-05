<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Enums\{StatusAgendamento, StatusCaixa, StatusParcela};
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Models\{BaixaDespesa, BaixaPagamento, Caixa};
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Pagamento\Models\ParcelaPagamento;
use App\Modules\Servico\Models\Servico;
use Illuminate\Database\Eloquent\Collection;

class DashboardService
{
    /**
     * Retorna os indicadores usados nos cards do dashboard.
     */
    public function indicadores(): array
    {
        return [
            'agendamentosHoje' => $this->agendamentosHoje(),
            'totalClientes' => $this->totalClientes(),
            'receitaMes' => $this->receitaMes(),
            'receitaMesAnterior' => $this->receitaMesAnterior(),
            'despesaMes' => $this->despesaMes(),
            'despesaMesAnterior' => $this->despesaMesAnterior(),
            'servicosAtivos' => $this->servicosAtivos(),
            'contasReceber' => $this->contasReceberQuantidade(),
            'totalContasReceber' => $this->contasReceberTotal(),
            'caixaAberto' => $this->caixaAberto(),
            'proximosAgendamentos' => $this->proximosAgendamentos(),
            'parcelasVencendo' => $this->parcelasVencendo(),
            'fluxoUltimos6Meses' => $this->fluxoUltimos6Meses(),
            'agendamentosPorStatusMes' => $this->agendamentosPorStatusMes(),
        ];
    }

    public function agendamentosHoje(): int
    {
        return Agendamento::whereDate('inicio', today())->count();
    }

    /**
     * Intencionalmente por rede: Cliente e catalogo da rede (nao ha empresa_id).
     * O EmpresaTrait nao e aplicado a Cliente, RedeTrait limita a rede do usuario.
     */
    public function totalClientes(): int
    {
        return Cliente::count();
    }

    public function receitaMes(): float
    {
        return (float) BaixaPagamento::whereMonth('data', now()->month)
            ->whereYear('data', now()->year)
            ->sum('valor');
    }

    public function receitaMesAnterior(): float
    {
        $ref = now()->copy()->subMonthNoOverflow();

        return (float) BaixaPagamento::whereMonth('data', $ref->month)
            ->whereYear('data', $ref->year)
            ->sum('valor');
    }

    public function despesaMes(): float
    {
        return (float) BaixaDespesa::whereMonth('data', now()->month)
            ->whereYear('data', now()->year)
            ->sum('valor');
    }

    public function despesaMesAnterior(): float
    {
        $ref = now()->copy()->subMonthNoOverflow();

        return (float) BaixaDespesa::whereMonth('data', $ref->month)
            ->whereYear('data', $ref->year)
            ->sum('valor');
    }

    /**
     * Intencionalmente por rede: Servico e catalogo da rede (sem empresa_id).
     */
    public function servicosAtivos(): int
    {
        return Servico::count();
    }

    public function contasReceberQuantidade(): int
    {
        return ParcelaPagamento::where('status', StatusParcela::Pendente)->count();
    }

    public function contasReceberTotal(): float
    {
        return (float) ParcelaPagamento::where('status', StatusParcela::Pendente)
            ->selectRaw('SUM(valor - valor_pago) as total')
            ->value('total') ?? 0;
    }

    public function caixaAberto(): ?Caixa
    {
        return Caixa::where('status', StatusCaixa::Aberto)->first();
    }

    /**
     * Proximos agendamentos do dia (a partir de agora) que ainda
     * estao em andamento — Agendado ou Confirmado. Limitado a 5.
     *
     * Respeita EmpresaTrait automaticamente (filtro pelas empresas
     * atualmente selecionadas na sessao).
     */
    public function proximosAgendamentos(int $limite = 5): Collection
    {
        return Agendamento::with(['cliente:id,nome', 'servico:id,nome'])
            ->whereIn('status', [StatusAgendamento::Agendado, StatusAgendamento::Confirmado])
            ->where('inicio', '>=', now())
            ->where('inicio', '<', now()->copy()->endOfDay())
            ->orderBy('inicio')
            ->limit($limite)
            ->get();
    }

    /**
     * Parcelas a receber vencendo nos proximos N dias (incluindo
     * hoje), em status Pendente ou ParcialmentePago. Limitado a 5.
     *
     * Decisao: focar so em "a receber" (Pagamento) por simplicidade
     * visual. Despesa fica de fora desta lista — a contraparte de
     * "alertas a pagar" e um futuro card proprio se necessario.
     *
     * Respeita EmpresaTrait via ParcelaPagamento.
     */
    public function parcelasVencendo(int $dias = 7, int $limite = 5): Collection
    {
        return ParcelaPagamento::with(['pagamento:id,cliente_id', 'pagamento.cliente:id,nome'])
            ->whereIn('status', [StatusParcela::Pendente, StatusParcela::Vencido])
            ->whereBetween('data_vencimento', [today(), today()->copy()->addDays($dias)])
            ->orderBy('data_vencimento')
            ->limit($limite)
            ->get();
    }

    /**
     * Receita (BaixaPagamento) e Despesa (BaixaDespesa) somadas por mes
     * nos ultimos 6 meses (do mais antigo ao mes atual). Usado pelo
     * grafico de fluxo financeiro.
     *
     * Respeita EmpresaTrait nos dois modelos.
     */
    public function fluxoUltimos6Meses(): array
    {
        $meses = collect();
        $cursor = now()->copy()->subMonths(5)->startOfMonth();
        $fim = now()->copy()->startOfMonth();

        while ($cursor->lte($fim)) {
            $receita = (float) BaixaPagamento::whereYear('data', $cursor->year)
                ->whereMonth('data', $cursor->month)
                ->sum('valor');
            $despesa = (float) BaixaDespesa::whereYear('data', $cursor->year)
                ->whereMonth('data', $cursor->month)
                ->sum('valor');

            $meses->push([
                'label' => ucfirst($cursor->locale('pt_BR')->isoFormat('MMM/YY')),
                'receita' => round($receita, 2),
                'despesa' => round($despesa, 2),
            ]);

            $cursor->addMonth();
        }

        return $meses->values()->all();
    }

    /**
     * Distribuicao dos agendamentos do mes vigente por status.
     * Usado pelo grafico donut.
     */
    public function agendamentosPorStatusMes(): array
    {
        $contagem = Agendamento::whereYear('inicio', now()->year)
            ->whereMonth('inicio', now()->month)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return collect(StatusAgendamento::cases())
            ->map(fn (StatusAgendamento $s) => [
                'status' => $s->value,
                'label' => $s->label(),
                'cor' => $s->cor(),
                'total' => (int) ($contagem[$s->value] ?? 0),
            ])
            ->all();
    }
}
