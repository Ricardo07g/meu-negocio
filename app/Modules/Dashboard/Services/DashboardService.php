<?php

namespace App\Modules\Dashboard\Services;

use App\Enums\StatusAgendamento;
use App\Enums\StatusCaixa;
use App\Enums\StatusParcela;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Models\BaixaPagamento;
use App\Modules\Caixa\Models\Caixa;
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
            'servicosAtivos' => $this->servicosAtivos(),
            'contasReceber' => $this->contasReceberQuantidade(),
            'totalContasReceber' => $this->contasReceberTotal(),
            'caixaAberto' => $this->caixaAberto(),
            'proximosAgendamentos' => $this->proximosAgendamentos(),
            'parcelasVencendo' => $this->parcelasVencendo(),
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
}
