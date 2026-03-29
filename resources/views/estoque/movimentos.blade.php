@extends('layouts.app')

@section('titulo', 'Movimentos de Estoque - Meu Negócio')
@section('titulo-pagina', 'Movimentos de Estoque')
@section('breadcrumb')
    <li class="breadcrumb-item active">Movimentos de Estoque</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title">Lista de Movimentos</h5>
            @can('movimento_estoque.criar')
            <a href="{{ route('movimentos-estoque.create') }}" class="btn btn-sm btn-primary">
                <i class="feather-plus me-1"></i> Novo Movimento
            </a>
            @endcan
        </div>
        <div class="card-body custom-card-action">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Tipo</th>
                            <th>Quantidade</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movimentos as $movimento)
                        <tr>
                            <td>{{ $movimento->produto->nome ?? '-' }}</td>
                            <td>
                                @switch($movimento->tipo->value)
                                    @case('entrada')
                                        <span class="badge bg-success">Entrada</span>
                                        @break
                                    @case('saida')
                                        <span class="badge bg-danger">Saída</span>
                                        @break
                                    @case('ajuste')
                                        <span class="badge bg-warning">Ajuste</span>
                                        @break
                                    @default
                                        <span class="badge bg-secondary">{{ ucfirst($movimento->tipo->value) }}</span>
                                @endswitch
                            </td>
                            <td>{{ $movimento->quantidade }}</td>
                            <td>{{ $movimento->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted">Nenhum movimento registrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
