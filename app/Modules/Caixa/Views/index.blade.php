@extends('layouts.app')

@section('titulo', 'Caixa - Meu Negócio')
@section('titulo-pagina', 'Caixa')
@section('breadcrumb')
    <li class="breadcrumb-item active">Caixa</li>
@endsection

@section('content')
    {{-- Button row OUTSIDE the card --}}
    @can('financeiro.criar')
    @php
        $caixaAbertoHoje = $caixas->where('data', today()->toDateString())->where('status', 'aberto')->first();
    @endphp
    @if(!$caixaAbertoHoje)
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('caixas.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Abrir Caixa
            </a>
        </div>
    </div>
    @endif
    @endcan

    {{-- Card with table --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Status</th>
                            <th>Saldo Abertura</th>
                            <th>Saldo Fechamento</th>
                            <th>Quem Abriu</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($caixas as $caixa)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($caixa->data)->format('d/m/Y') }}</td>
                            <td>
                                @if($caixa->status === 'aberto')
                                    <span class="badge bg-success">Aberto</span>
                                @else
                                    <span class="badge bg-secondary">Fechado</span>
                                @endif
                            </td>
                            <td>R$ {{ number_format($caixa->saldo_abertura, 2, ',', '.') }}</td>
                            <td>
                                @if($caixa->saldo_fechamento !== null)
                                    R$ {{ number_format($caixa->saldo_fechamento, 2, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $caixa->usuario->nome ?? '-' }}</td>
                            <td>
                                <div class="hstack gap-2 justify-content-end">
                                    <a href="{{ route('caixas.show', $caixa) }}" class="avatar-text avatar-md" title="Ver">
                                        <i class="feather-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum caixa registrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
