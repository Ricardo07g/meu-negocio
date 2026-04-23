<?php

namespace App\Modules\Dashboard\Services;

use App\Enums\StatusCaixa;
use App\Enums\StatusParcela;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Models\BaixaPagamento;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Pagamento\Models\ParcelaPagamento;
use App\Modules\Servico\Models\Servico;

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
        ];
    }

    public function agendamentosHoje(): int
    {
        return Agendamento::whereDate('inicio', today())->count();
    }

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
}
