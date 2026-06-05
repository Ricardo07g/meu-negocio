@extends('layouts.app')

@section('titulo', 'Início - Meu Negócio')
@section('titulo-pagina', 'Início')

@php
    use Carbon\Carbon;

    $deltaPercentual = function (float $atual, float $anterior): ?float {
        if ($anterior <= 0) {
            return $atual > 0 ? 100.0 : null;
        }
        return round((($atual - $anterior) / $anterior) * 100, 1);
    };

    $deltaReceita = $deltaPercentual($receitaMes, $receitaMesAnterior);
    $deltaDespesa = $deltaPercentual($despesaMes, $despesaMesAnterior);

    // Para receita: subir e bom (verde). Para despesa: subir e ruim (vermelho).
    $classeDeltaReceita = $deltaReceita === null ? 'text-muted' : ($deltaReceita >= 0 ? 'text-success' : 'text-danger');
    $classeDeltaDespesa = $deltaDespesa === null ? 'text-muted' : ($deltaDespesa <= 0 ? 'text-success' : 'text-danger');

    $iconeDeltaReceita = $deltaReceita === null ? 'feather-minus' : ($deltaReceita >= 0 ? 'feather-trending-up' : 'feather-trending-down');
    $iconeDeltaDespesa = $deltaDespesa === null ? 'feather-minus' : ($deltaDespesa >= 0 ? 'feather-trending-up' : 'feather-trending-down');

    $usuario = auth()->user();
    $primeiroNome = explode(' ', trim($usuario->nome ?? ''))[0] ?? '';
@endphp

@push('css')
<style>
    .dash-saudacao { font-size: 1.05rem; }
    .dash-saudacao .data { color: #6c757d; font-size: .85rem; }

    .kpi-card .kpi-label { font-size: .72rem; font-weight: 600; color: #6c757d; text-transform: uppercase; letter-spacing: .04em; }
    .kpi-card .kpi-valor { font-size: 1.65rem; font-weight: 700; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .kpi-card .kpi-delta { font-size: .78rem; font-weight: 600; display: inline-flex; align-items: center; gap: .2rem; }
    .kpi-card .kpi-delta i { font-size: 14px; }
    .kpi-card .kpi-icon {
        width: 44px; height: 44px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .kpi-card .kpi-icon i { font-size: 18px; }

    .kpi-mini-card .kpi-mini-icon {
        width: 36px; height: 36px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
    }

    .chart-card .card-body { min-height: 320px; }
</style>
@endpush

@section('content')
    {{-- Filtro de empresa --}}
    @include('partials.filtro-empresa-listagem')

    {{-- Saudacao + data --}}
    <div class="dash-saudacao mb-3">
        <span class="fw-semibold">Ola, {{ $primeiroNome ?: $usuario->nome }}!</span>
        <span class="data ms-2">{{ ucfirst(now()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM [de] YYYY')) }}</span>
    </div>

    {{-- KPIs principais --}}
    <div class="row g-3">
        {{-- Agendamentos hoje --}}
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full kpi-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label mb-2">Agendamentos hoje</div>
                            <div class="kpi-valor text-primary">{{ $agendamentosHoje }}</div>
                            <div class="kpi-delta text-muted mt-2">
                                <i class="feather-calendar"></i>
                                <span>{{ now()->locale('pt_BR')->isoFormat('dddd') }}</span>
                            </div>
                        </div>
                        <span class="kpi-icon bg-soft-primary text-primary">
                            <i class="feather-calendar"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Receita do mes --}}
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full kpi-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label mb-2">Receita do mes</div>
                            <div class="kpi-valor text-success">R$ {{ number_format($receitaMes, 2, ',', '.') }}</div>
                            <div class="kpi-delta {{ $classeDeltaReceita }} mt-2">
                                <i class="{{ $iconeDeltaReceita }}"></i>
                                @if ($deltaReceita === null)
                                    <span>sem dados anteriores</span>
                                @else
                                    <span>{{ $deltaReceita >= 0 ? '+' : '' }}{{ number_format($deltaReceita, 1, ',', '.') }}% vs mes anterior</span>
                                @endif
                            </div>
                        </div>
                        <span class="kpi-icon bg-soft-success text-success">
                            <i class="feather-trending-up"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Despesas do mes --}}
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full kpi-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label mb-2">Despesas do mes</div>
                            <div class="kpi-valor text-danger">R$ {{ number_format($despesaMes, 2, ',', '.') }}</div>
                            <div class="kpi-delta {{ $classeDeltaDespesa }} mt-2">
                                <i class="{{ $iconeDeltaDespesa }}"></i>
                                @if ($deltaDespesa === null)
                                    <span>sem dados anteriores</span>
                                @else
                                    <span>{{ $deltaDespesa >= 0 ? '+' : '' }}{{ number_format($deltaDespesa, 1, ',', '.') }}% vs mes anterior</span>
                                @endif
                            </div>
                        </div>
                        <span class="kpi-icon bg-soft-danger text-danger">
                            <i class="feather-trending-down"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Caixa atual --}}
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full kpi-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label mb-2">Caixa atual</div>
                            @if ($caixaAberto)
                                @php $saldo = (float) $caixaAberto->saldo_abertura; @endphp
                                <div class="kpi-valor text-info">R$ {{ number_format($saldo, 2, ',', '.') }}</div>
                                <div class="kpi-delta text-success mt-2">
                                    <i class="feather-unlock"></i>
                                    <span>Aberto em {{ $caixaAberto->data instanceof \Carbon\Carbon ? $caixaAberto->data->format('d/m/Y') : Carbon::parse($caixaAberto->data)->format('d/m/Y') }}</span>
                                </div>
                            @else
                                <div class="kpi-valor text-muted">Fechado</div>
                                <div class="kpi-delta text-muted mt-2">
                                    <i class="feather-lock"></i>
                                    <span>Sem caixa aberto hoje</span>
                                </div>
                            @endif
                        </div>
                        <span class="kpi-icon bg-soft-info text-info">
                            <i class="feather-credit-card"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- KPIs secundarios --}}
    <div class="row g-3 mt-1">
        <div class="col-md-4">
            <div class="card stretch stretch-full kpi-mini-card">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="kpi-mini-icon bg-soft-success text-success">
                            <i class="feather-users"></i>
                        </span>
                        <div>
                            <div class="fs-12 text-muted fw-semibold">Total de clientes</div>
                            <div class="fw-bold fs-18 lh-1 mt-1">{{ $totalClientes }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stretch stretch-full kpi-mini-card">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="kpi-mini-icon bg-soft-info text-info">
                            <i class="feather-briefcase"></i>
                        </span>
                        <div>
                            <div class="fs-12 text-muted fw-semibold">Servicos ativos</div>
                            <div class="fw-bold fs-18 lh-1 mt-1">{{ $servicosAtivos }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stretch stretch-full kpi-mini-card">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="kpi-mini-icon bg-soft-warning text-warning">
                            <i class="feather-alert-circle"></i>
                        </span>
                        <div>
                            <div class="fs-12 text-muted fw-semibold">Contas a receber</div>
                            <div class="fw-bold fs-18 lh-1 mt-1">
                                R$ {{ number_format($totalContasReceber, 2, ',', '.') }}
                                <small class="text-muted fs-11 fw-normal">({{ $contasReceber }} parcela(s))</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Graficos --}}
    <div class="row g-3 mt-1">
        <div class="col-lg-8">
            <div class="card stretch stretch-full chart-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Fluxo financeiro</h5>
                        <small class="text-muted">Receita x Despesa - ultimos 6 meses</small>
                    </div>
                    <div class="d-flex gap-3 align-items-center fs-12">
                        <span><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:#25b865;"></span>Receita</span>
                        <span><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:#d13b4c;"></span>Despesa</span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="chart-fluxo"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card stretch stretch-full chart-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Agendamentos por status</h5>
                    <small class="text-muted">Mes vigente</small>
                </div>
                <div class="card-body">
                    <div id="chart-status"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabelas --}}
    <div class="row g-3 mt-1">
        {{-- Proximos agendamentos --}}
        <div class="col-xxl-6">
            <div class="card stretch stretch-full">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Proximos agendamentos</h5>
                    <a href="{{ route('agenda.index') }}" class="btn btn-sm btn-light">
                        <i class="feather-external-link me-1"></i>Ver agenda
                    </a>
                </div>
                <div class="card-body p-0">
                    @if ($proximosAgendamentos->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="feather-inbox fs-1"></i>
                            <p class="mb-0 mt-2">Nenhum agendamento para o restante de hoje.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Hora</th>
                                        <th>Cliente</th>
                                        <th>Servico</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($proximosAgendamentos as $ag)
                                        <tr>
                                            <td class="fw-semibold">{{ $ag->inicio->format('H:i') }}</td>
                                            <td>{{ $ag->cliente->nome ?? '-' }}</td>
                                            <td>{{ $ag->servico->nome ?? '-' }}</td>
                                            <td>
                                                <span class="badge bg-{{ $ag->status->cor() }}">{{ $ag->status->label() }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Parcelas vencendo --}}
        <div class="col-xxl-6">
            <div class="card stretch stretch-full">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Parcelas vencendo (7 dias)</h5>
                    <a href="{{ route('pagamentos.index') }}" class="btn btn-sm btn-light">
                        <i class="feather-external-link me-1"></i>Ver todas
                    </a>
                </div>
                <div class="card-body p-0">
                    @if ($parcelasVencendo->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="feather-check-circle fs-1 text-success"></i>
                            <p class="mb-0 mt-2">Nenhuma parcela vencendo nos proximos 7 dias.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Vencimento</th>
                                        <th>Cliente</th>
                                        <th class="text-end">Valor</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($parcelasVencendo as $p)
                                        <tr>
                                            <td>{{ $p->data_vencimento->format('d/m/Y') }}</td>
                                            <td>{{ $p->pagamento->cliente->nome ?? '-' }}</td>
                                            <td class="text-end fw-semibold">R$ {{ number_format($p->valor - $p->valor_pago, 2, ',', '.') }}</td>
                                            <td>
                                                @php
                                                    $cor = $p->status === \App\Enums\StatusParcela::Vencido ? 'danger' : 'warning';
                                                @endphp
                                                <span class="badge bg-{{ $cor }}">{{ ucfirst($p->status->value) }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
<script src="{{ asset('assets/vendors/js/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof ApexCharts === 'undefined') {
        console.error('[dashboard] ApexCharts nao carregou');
        return;
    }

    const fluxo = @json($fluxoUltimos6Meses);
    const labelsFluxo = fluxo.map(m => m.label);
    const receitas = fluxo.map(m => m.receita);
    const despesas = fluxo.map(m => m.despesa);

    const elFluxo = document.querySelector('#chart-fluxo');
    if (elFluxo) {
        new ApexCharts(elFluxo, {
            chart: { type: 'bar', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [
                { name: 'Receita', data: receitas },
                { name: 'Despesa', data: despesas },
            ],
            xaxis: { categories: labelsFluxo, labels: { style: { fontSize: '12px' } } },
            colors: ['#25b865', '#d13b4c'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
            dataLabels: { enabled: false },
            legend: { show: false },
            grid: { strokeDashArray: 4, borderColor: '#e9ecef' },
            yaxis: {
                labels: {
                    style: { fontSize: '11px' },
                    formatter: function (v) {
                        if (v >= 1000) return 'R$ ' + (v / 1000).toFixed(1) + 'k';
                        return 'R$ ' + Number(v).toFixed(0);
                    },
                },
            },
            tooltip: {
                y: {
                    formatter: function (v) {
                        return 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },
                },
            },
        }).render();
    }

    const status = @json($agendamentosPorStatusMes);
    const seriesStatus = status.map(s => s.total);
    const labelsStatus = status.map(s => s.label);
    const corPorStatus = { agendado: '#17a2b8', confirmado: '#3454d1', finalizado: '#25b865', cancelado: '#d13b4c' };
    const coresStatus = status.map(s => corPorStatus[s.status] || '#6c757d');

    const elStatus = document.querySelector('#chart-status');
    if (elStatus) {
        const totalStatus = seriesStatus.reduce((a, b) => a + b, 0);
        if (totalStatus === 0) {
            elStatus.innerHTML = '<div class="text-center text-muted py-5"><i class="feather-inbox fs-1"></i><p class="mb-0 mt-2">Nenhum agendamento neste mes.</p></div>';
        } else {
            new ApexCharts(elStatus, {
                chart: { type: 'donut', height: 320, fontFamily: 'inherit' },
                series: seriesStatus,
                labels: labelsStatus,
                colors: coresStatus,
                legend: { position: 'bottom', fontSize: '12px' },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '70%',
                            labels: {
                                show: true,
                                total: { show: true, label: 'Total', fontSize: '13px', color: '#6c757d', formatter: () => totalStatus },
                                value: { fontSize: '20px', fontWeight: 700 },
                            },
                        },
                    },
                },
                dataLabels: { enabled: false },
                stroke: { width: 2, colors: ['#fff'] },
                tooltip: { y: { formatter: function (v) { return v + ' agendamento(s)'; } } },
            }).render();
        }
    }
});
</script>
@endpush
