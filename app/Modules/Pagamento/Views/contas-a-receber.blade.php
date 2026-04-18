@extends('layouts.app')

@section('titulo', 'Contas a Receber - Meu Negócio')
@section('titulo-pagina', 'Contas a Receber')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('pagamentos.index') }}">Pagamentos</a></li>
    <li class="breadcrumb-item active">Contas a Receber</li>
@endsection

@section('content')
    {{-- Totalizador --}}
    <div class="row mb-4">
        <div class="col-xxl-4 col-md-6">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fs-12 text-muted mb-1">Total a Receber</div>
                            <h4 class="fw-bold mb-0 text-danger">R$ {{ number_format($totalReceber, 2, ',', '.') }}</h4>
                            <small class="text-muted">{{ $pagamentos->count() }} pagamento(s) pendente(s)</small>
                        </div>
                        <div class="wd-40 ht-40 bg-soft-danger rounded-circle d-flex align-items-center justify-content-center">
                            <i class="feather-alert-circle text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Valor Total</th>
                            <th>Valor Pago</th>
                            <th>Saldo Devedor</th>
                            <th>Data</th>
                            <th>Dias em Atraso</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pagamentos as $pagamento)
                        @php
                            $saldo = $pagamento->valor - $pagamento->valor_pago;
                            $diasAtraso = $pagamento->created_at->diffInDays(now());
                        @endphp
                        <tr>
                            <td>{{ $pagamento->cliente->nome ?? '-' }}</td>
                            <td>R$ {{ number_format($pagamento->valor, 2, ',', '.') }}</td>
                            <td>R$ {{ number_format($pagamento->valor_pago, 2, ',', '.') }}</td>
                            <td class="text-danger fw-bold">R$ {{ number_format($saldo, 2, ',', '.') }}</td>
                            <td>{{ $pagamento->created_at->format('d/m/Y') }}</td>
                            <td>
                                @if($diasAtraso > 30)
                                    <span class="badge bg-danger">{{ $diasAtraso }} dias</span>
                                @elseif($diasAtraso > 7)
                                    <span class="badge bg-warning">{{ $diasAtraso }} dias</span>
                                @else
                                    <span class="badge bg-info">{{ $diasAtraso }} dias</span>
                                @endif
                            </td>
                            <td>
                                <div class="hstack gap-2 justify-content-end">
                                    <a href="{{ route('pagamentos.show', $pagamento) }}" class="btn btn-sm btn-primary">
                                        <i class="feather-dollar-sign me-1"></i>Receber
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhuma conta a receber.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
