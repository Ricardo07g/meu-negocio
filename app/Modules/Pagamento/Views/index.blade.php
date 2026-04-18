@extends('layouts.app')

@section('titulo', 'Pagamentos - Meu Negócio')
@section('titulo-pagina', 'Pagamentos')
@section('breadcrumb')
    <li class="breadcrumb-item active">Pagamentos</li>
@endsection

@section('content')
    {{-- Filtros --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="hstack gap-2">
                <a href="{{ route('pagamentos.index') }}" class="btn btn-sm {{ $filtro === 'todos' ? 'btn-primary' : 'btn-outline-primary' }}">Todos</a>
                <a href="{{ route('pagamentos.index', ['status' => 'pendente']) }}" class="btn btn-sm {{ $filtro === 'pendente' ? 'btn-warning' : 'btn-outline-warning' }}">Pendentes</a>
                <a href="{{ route('pagamentos.index', ['status' => 'pago']) }}" class="btn btn-sm {{ $filtro === 'pago' ? 'btn-success' : 'btn-outline-success' }}">Pagos</a>
                <a href="{{ route('pagamentos.index', ['status' => 'estornado']) }}" class="btn btn-sm {{ $filtro === 'estornado' ? 'btn-secondary' : 'btn-outline-secondary' }}">Estornados</a>
            </div>
        </div>
    </div>

    {{-- Card with table --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Valor</th>
                            <th>Pago</th>
                            <th>Restante</th>
                            <th>Forma</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pagamentos as $pagamento)
                        @php $restante = $pagamento->valor - $pagamento->valor_pago; @endphp
                        <tr>
                            <td>{{ $pagamento->cliente->nome ?? '-' }}</td>
                            <td>R$ {{ number_format($pagamento->valor, 2, ',', '.') }}</td>
                            <td>R$ {{ number_format($pagamento->valor_pago, 2, ',', '.') }}</td>
                            <td class="{{ $restante > 0 ? 'text-danger fw-bold' : '' }}">R$ {{ number_format($restante, 2, ',', '.') }}</td>
                            <td>{{ ucfirst($pagamento->forma_pagamento->value) }}</td>
                            <td>
                                @switch($pagamento->status->value)
                                    @case('pago')
                                        <span class="badge bg-success">Pago</span>
                                        @break
                                    @case('pendente')
                                        <span class="badge bg-warning">Pendente</span>
                                        @break
                                    @case('cancelado')
                                        <span class="badge bg-danger">Cancelado</span>
                                        @break
                                    @case('estornado')
                                        <span class="badge bg-secondary">Estornado</span>
                                        @break
                                @endswitch
                            </td>
                            <td>{{ $pagamento->created_at->format('d/m/Y') }}</td>
                            <td>
                                <div class="hstack gap-2 justify-content-end">
                                    <a href="{{ route('pagamentos.show', $pagamento) }}" class="avatar-text avatar-md" title="Ver">
                                        <i class="feather-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhum pagamento encontrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
