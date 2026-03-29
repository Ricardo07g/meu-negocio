@extends('layouts.app')

@section('titulo', 'Produto - Meu Negócio')
@section('titulo-pagina', $produto->nome)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('produtos.index') }}">Produtos</a></li>
    <li class="breadcrumb-item active">{{ $produto->nome }}</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title">Dados do Produto</h5>
            @can('produto.editar')
            <a href="{{ route('produtos.edit', $produto) }}" class="btn btn-sm btn-primary"><i class="feather-edit me-1"></i> Editar</a>
            @endcan
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3"><strong>Nome:</strong> {{ $produto->nome }}</div>
                <div class="col-md-4 mb-3"><strong>Quantidade:</strong> {{ $produto->quantidade }}</div>
                <div class="col-md-4 mb-3"><strong>Valor:</strong> R$ {{ number_format($produto->valor, 2, ',', '.') }}</div>
            </div>
        </div>
    </div>

    {{-- Movimentos de Estoque --}}
    <div class="card stretch stretch-full mt-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title">Movimentos de Estoque</h5>
            @can('movimento_estoque.criar')
            <a href="{{ route('movimentos-estoque.create', ['produto_id' => $produto->id]) }}" class="btn btn-sm btn-primary">
                <i class="feather-plus me-1"></i> Novo Movimento
            </a>
            @endcan
        </div>
        <div class="card-body custom-card-action">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Quantidade</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($produto->movimentos ?? [] as $movimento)
                        <tr>
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
                        <tr><td colspan="3" class="text-center text-muted">Nenhum movimento registrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
