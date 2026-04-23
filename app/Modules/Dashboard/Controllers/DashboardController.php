<?php

namespace App\Modules\Dashboard\Controllers;

use App\Enums\StatusParcela;
use App\Http\Controllers\Controller;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Models\BaixaPagamento;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Pagamento\Models\ParcelaPagamento;
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

            $receitaMes = (float) BaixaPagamento::whereMonth('data', now()->month)
                ->whereYear('data', now()->year)
                ->sum('valor');

            $servicosAtivos = Servico::count();

            $parcelasPendentes = ParcelaPagamento::where('status', StatusParcela::Pendente);
            $contasReceber = (clone $parcelasPendentes)->count();
            $totalContasReceber = (float) (clone $parcelasPendentes)
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
