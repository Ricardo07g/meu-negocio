<?php

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Enums\StatusPagamento;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Servico\Models\Servico;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use TratamentoErros;

    public function index(): View|RedirectResponse
    {
        try {
            $agendamentosHoje = Agendamento::whereDate('inicio', today())->count();
            $totalClientes = Cliente::count();
            $receitaMes = Pagamento::where('status', StatusPagamento::Pago)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('valor_pago');
            $servicosAtivos = Servico::count();

            $contasReceber = Pagamento::where('status', StatusPagamento::Pendente)->count();
            $totalContasReceber = Pagamento::where('status', StatusPagamento::Pendente)
                ->selectRaw('SUM(valor - valor_pago) as total')
                ->value('total') ?? 0;

            $caixaAberto = Caixa::where('status', 'aberto')->first();

            return view('dashboard::dashboard', compact(
                'agendamentosHoje',
                'totalClientes',
                'receitaMes',
                'servicosAtivos',
                'contasReceber',
                'totalContasReceber',
                'caixaAberto',
            ));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar dashboard');
        }
    }
}
