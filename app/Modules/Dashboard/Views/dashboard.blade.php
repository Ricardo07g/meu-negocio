@extends('layouts.app')

@section('titulo', 'Início - Meu Negócio')
@section('titulo-pagina', 'Início')

@section('content')
    <div class="row">
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fs-12 text-muted mb-1">Agendamentos Hoje <span class="fs-11 text-muted">(empresas atuais)</span></div>
                            <h5 class="fw-bold mb-0">{{ $agendamentosHoje }}</h5>
                        </div>
                        <div class="wd-40 ht-40 bg-soft-primary rounded-circle d-flex align-items-center justify-content-center">
                            <i class="feather-calendar text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fs-12 text-muted mb-1">Total Clientes <span class="fs-11 text-muted">(rede)</span></div>
                            <h5 class="fw-bold mb-0">{{ $totalClientes }}</h5>
                        </div>
                        <div class="wd-40 ht-40 bg-soft-success rounded-circle d-flex align-items-center justify-content-center">
                            <i class="feather-users text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fs-12 text-muted mb-1">Receita do Mês <span class="fs-11 text-muted">(empresas atuais)</span></div>
                            <h5 class="fw-bold mb-0 text-success">R$ {{ number_format($receitaMes, 2, ',', '.') }}</h5>
                        </div>
                        <div class="wd-40 ht-40 bg-soft-warning rounded-circle d-flex align-items-center justify-content-center">
                            <i class="feather-dollar-sign text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fs-12 text-muted mb-1">Serviços Ativos <span class="fs-11 text-muted">(rede)</span></div>
                            <h5 class="fw-bold mb-0">{{ $servicosAtivos }}</h5>
                        </div>
                        <div class="wd-40 ht-40 bg-soft-info rounded-circle d-flex align-items-center justify-content-center">
                            <i class="feather-briefcase text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        {{-- Contas a Receber --}}
        <div class="col-xxl-4 col-md-6">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fs-12 text-muted mb-1">Contas a Receber <span class="fs-11 text-muted">(empresas atuais)</span></div>
                            <h5 class="fw-bold mb-0 text-danger">R$ {{ number_format($totalContasReceber, 2, ',', '.') }}</h5>
                            <small class="text-muted">{{ $contasReceber }} pagamento(s) pendente(s)</small>
                        </div>
                        <div class="wd-40 ht-40 bg-soft-danger rounded-circle d-flex align-items-center justify-content-center">
                            <i class="feather-alert-circle text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Caixa --}}
        <div class="col-xxl-4 col-md-6">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fs-12 text-muted mb-1">Caixa <span class="fs-11 text-muted">(empresas atuais)</span></div>
                            @if($caixaAberto)
                                <h5 class="fw-bold mb-0 text-success">Aberto</h5>
                                <small class="text-muted">Abertura: R$ {{ number_format($caixaAberto->saldo_abertura, 2, ',', '.') }}</small>
                            @else
                                <h5 class="fw-bold mb-0 text-secondary">Fechado</h5>
                                <small class="text-muted">Nenhum caixa aberto</small>
                            @endif
                        </div>
                        <div class="wd-40 ht-40 bg-soft-success rounded-circle d-flex align-items-center justify-content-center">
                            <i class="feather-inbox text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        {{-- Proximos Agendamentos de Hoje --}}
        <div class="col-xxl-6 col-md-12">
            <div class="card stretch stretch-full">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title">Próximos Agendamentos de Hoje</h5>
                    <a href="{{ route('agenda.index') }}" class="text-muted small"><i class="feather-external-link"></i> Ver todos</a>
                </div>
                <div class="card-body p-0">
                    @if($proximosAgendamentos->isEmpty())
                        <div class="text-center text-muted py-5">
                            <i class="feather-calendar fs-1 d-block mb-2"></i>
                            <p class="mb-0">Nenhum agendamento restante para hoje.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Hora</th>
                                        <th>Cliente</th>
                                        <th>Serviço</th>
                                        <th class="text-end">&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($proximosAgendamentos as $agendamento)
                                        <tr>
                                            <td><strong>{{ $agendamento->inicio->format('H:i') }}</strong></td>
                                            <td>{{ $agendamento->cliente->nome ?? '—' }}</td>
                                            <td>{{ $agendamento->servico->nome ?? '—' }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('agenda.show', $agendamento) }}" class="btn btn-sm btn-light-primary"><i class="feather-eye"></i></a>
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

        {{-- Parcelas Vencendo (7 dias) --}}
        <div class="col-xxl-6 col-md-12">
            <div class="card stretch stretch-full">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title">Parcelas Vencendo (7 dias)</h5>
                    <a href="{{ route('pagamentos.contas-a-receber') }}" class="text-muted small"><i class="feather-external-link"></i> Contas a Receber</a>
                </div>
                <div class="card-body p-0">
                    @if($parcelasVencendo->isEmpty())
                        <div class="text-center text-muted py-5">
                            <i class="feather-check-circle fs-1 d-block mb-2"></i>
                            <p class="mb-0">Nenhuma parcela vencendo nos próximos 7 dias.</p>
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
                                        <th class="text-end">&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($parcelasVencendo as $parcela)
                                        <tr>
                                            <td>{{ $parcela->data_vencimento->format('d/m/Y') }}</td>
                                            <td>{{ $parcela->pagamento?->cliente?->nome ?? '—' }}</td>
                                            <td class="text-end">R$ {{ number_format($parcela->valor - $parcela->valor_pago, 2, ',', '.') }}</td>
                                            <td>
                                                <span class="badge bg-{{ $parcela->status->cor() }}">{{ $parcela->status->label() }}</span>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('parcelas-pagamento.baixa-form', $parcela) }}" class="btn btn-sm btn-light-success"><i class="feather-dollar-sign"></i></a>
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

    <div class="row mt-4">
        <div class="col-12">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title">Bem-vindo ao Meu Negócio!</h5>
                </div>
                <div class="card-body">
                    <p>Sistema pronto para uso. Comece cadastrando seus <a href="{{ route('servicos.index') }}">serviços</a> e <a href="{{ route('clientes.index') }}">clientes</a>.</p>
                    <p><strong>Plano atual:</strong> {{ auth()->user()->rede->plano->nome }}</p>
                    <p><strong>Empresa:</strong> {{ auth()->user()->empresa->nome ?? 'N/A' }}</p>
                    <p><strong>Papel:</strong> {{ auth()->user()->getRoleNames()->first() ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
