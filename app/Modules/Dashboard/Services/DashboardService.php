<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Enums\{StatusAgendamento, StatusCaixa, StatusParcela};
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Models\{BaixaDespesa, BaixaPagamento, Caixa};
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Conta\Models\Conta;
use App\Modules\Pagamento\Models\ParcelaPagamento;
use App\Modules\Servico\Models\Servico;
use Illuminate\Database\Eloquent\{Builder, Collection};
use Illuminate\Support\Facades\DB;

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
            'saldoPorConta' => $this->saldoPorConta(),
            'proximosAgendamentos' => $this->proximosAgendamentos(),
            'parcelasVencendo' => $this->parcelasVencendo(),
            'fluxoUltimos6Meses' => $this->fluxoUltimos6Meses(),
            'agendamentosPorStatusMes' => $this->agendamentosPorStatusMes(),
        ];
    }

    /**
     * Contas ativas da empresa (gaveta primeiro). So a gaveta (Caixa) tem saldo
     * controlado; banco/carteira sao rotulos de origem (ADR-0011) — a view mostra
     * o saldo so da gaveta.
     *
     * @return Collection<int, Conta>
     */
    public function saldoPorConta(): Collection
    {
        return Conta::ativas()->orderByDesc('eh_caixa_padrao')->orderBy('nome')->get();
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

    /**
     * Receita do mes: o que o cliente efetivamente pagou, liquido.
     *
     * Baixa estornada NAO conta — venda cancelada nao e faturamento. Mesma regra
     * de `Pagamento::totalRecebidoLiquido()`, do `ResumoDiaService` e do extrato
     * da conta; sem ela o dashboard anunciava receita de uma venda desfeita
     * enquanto o caixa do mesmo dia mostrava resultado zero.
     */
    public function receitaMes(): float
    {
        return $this->somaLiquida(
            BaixaPagamento::whereNull('estornado_em')
                ->whereMonth('data', now()->month)
                ->whereYear('data', now()->year)
        );
    }

    public function receitaMesAnterior(): float
    {
        $ref = now()->copy()->subMonthNoOverflow();

        return $this->somaLiquida(
            BaixaPagamento::whereNull('estornado_em')
                ->whereMonth('data', $ref->month)
                ->whereYear('data', $ref->year)
        );
    }

    /**
     * Despesa nao tem estorno: `BaixaDespesa` usa SoftDeletes, e o global scope
     * ja tira as apagadas.
     */
    public function despesaMes(): float
    {
        return $this->somaLiquida(
            BaixaDespesa::whereMonth('data', now()->month)
                ->whereYear('data', now()->year)
        );
    }

    public function despesaMesAnterior(): float
    {
        $ref = now()->copy()->subMonthNoOverflow();

        return $this->somaLiquida(
            BaixaDespesa::whereMonth('data', $ref->month)
                ->whereYear('data', $ref->year)
        );
    }

    /**
     * Soma o valor LIQUIDO das baixas da query: principal + multa + juros −
     * desconto, a mesma conta de `BaixaPagamento::valorTotal()`. Somar so
     * `valor` fazia o dashboard divergir do caixa e do extrato em toda parcela
     * recebida com juros, multa ou desconto.
     *
     * @param  Builder<BaixaPagamento>|Builder<BaixaDespesa>  $query
     */
    private function somaLiquida(Builder $query): float
    {
        return round((float) $query->sum(DB::raw('valor + multa + juros - desconto')), 2);
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
        // Caixa aberto de HOJE (na empresa em contexto, via EmpresaTrait) —
        // evita exibir um caixa de dia anterior deixado em aberto.
        return Caixa::where('status', StatusCaixa::Aberto)
            ->whereDate('data', today()->toDateString())
            ->first();
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
     * Mesma conta dos cards do topo (`somaLiquida`): liquido e sem estorno —
     * senao a curva contradiz o numero exibido logo acima dela.
     *
     * Respeita EmpresaTrait nos dois modelos.
     */
    public function fluxoUltimos6Meses(): array
    {
        $meses = collect();
        $cursor = now()->copy()->subMonths(5)->startOfMonth();
        $fim = now()->copy()->startOfMonth();

        while ($cursor->lte($fim)) {
            $receita = $this->somaLiquida(
                BaixaPagamento::whereNull('estornado_em')
                    ->whereYear('data', $cursor->year)
                    ->whereMonth('data', $cursor->month)
            );
            $despesa = $this->somaLiquida(
                BaixaDespesa::whereYear('data', $cursor->year)
                    ->whereMonth('data', $cursor->month)
            );

            $meses->push([
                'label' => ucfirst($cursor->locale('pt_BR')->isoFormat('MMM/YY')),
                'receita' => $receita,
                'despesa' => $despesa,
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
