@extends('layouts.app')

@section('titulo', 'Pagamentos - Meu Negócio')
@section('titulo-pagina', 'Pagamentos')
@section('breadcrumb')
    <li class="breadcrumb-item active">Pagamentos</li>
@endsection

@section('content')
    {{-- Button row OUTSIDE the card --}}
    @can('pagamento.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('pagamentos.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Novo Pagamento
            </a>
        </div>
    </div>
    @endcan

    {{-- Card with table --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Valor</th>
                            <th>Forma de Pagamento</th>
                            <th>Status</th>
                            <th>Cliente</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pagamentos as $pagamento)
                        <tr>
                            <td>R$ {{ number_format($pagamento->valor, 2, ',', '.') }}</td>
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
                                    @default
                                        <span class="badge bg-secondary">{{ ucfirst($pagamento->status->value) }}</span>
                                @endswitch
                            </td>
                            <td>{{ $pagamento->cliente->nome ?? '-' }}</td>
                            <td>
                                <div class="hstack gap-2 justify-content-end">
                                    <div class="dropdown">
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('pagamentos.show', $pagamento) }}">
                                                    <i class="feather-eye me-3"></i>
                                                    <span>Ver</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhum pagamento cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
