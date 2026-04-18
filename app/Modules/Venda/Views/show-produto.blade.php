@extends('layouts.app')

@section('titulo', 'Venda de Produto - Meu Negócio')
@section('titulo-pagina', 'Detalhes da Venda')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('vendas.index') }}">Vendas</a></li>
    <li class="breadcrumb-item active">Detalhes</li>
@endsection

@section('content')
    {{-- Cabecalho --}}
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Venda de Produto #{{ $vendaProduto->id }}</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3"><strong>Cliente:</strong> {{ $vendaProduto->cliente->nome ?? '-' }}</div>
                <div class="col-md-4 mb-3"><strong>Vendedor:</strong> {{ $vendaProduto->usuario->nome ?? '-' }}</div>
                <div class="col-md-4 mb-3"><strong>Data:</strong> {{ $vendaProduto->data ? $vendaProduto->data->format('d/m/Y') : $vendaProduto->created_at->format('d/m/Y') }}</div>
                <div class="col-md-4 mb-3"><strong>Valor Total:</strong> R$ {{ number_format($vendaProduto->valor_total, 2, ',', '.') }}</div>
                <div class="col-md-4 mb-3">
                    <strong>Status:</strong>
                    @if($vendaProduto->status === 'ativa')
                        <span class="badge bg-success">Ativa</span>
                    @elseif($vendaProduto->status === 'cancelada')
                        <span class="badge bg-danger">Cancelada</span>
                    @else
                        <span class="badge bg-secondary">{{ ucfirst($vendaProduto->status) }}</span>
                    @endif
                </div>
                @if($vendaProduto->observacao)
                <div class="col-12 mb-3"><strong>Observação:</strong> {{ $vendaProduto->observacao }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Itens --}}
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Itens da Venda</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-end">Valor Unit.</th>
                            <th class="text-end">Desconto</th>
                            <th class="text-end">Acréscimo</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vendaProduto->itens as $item)
                        <tr>
                            <td>{{ $item->descricao }}</td>
                            <td class="text-center">{{ $item->quantidade }}</td>
                            <td class="text-end">R$ {{ number_format($item->valor_unitario, 2, ',', '.') }}</td>
                            <td class="text-end">{{ $item->desconto > 0 ? 'R$ ' . number_format($item->desconto, 2, ',', '.') : '-' }}</td>
                            <td class="text-end">{{ $item->acrescimo > 0 ? 'R$ ' . number_format($item->acrescimo, 2, ',', '.') : '-' }}</td>
                            <td class="text-end">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="5" class="text-end">Total:</td>
                            <td class="text-end">R$ {{ number_format($vendaProduto->valor_total, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 text-center pt-4">
        <a href="{{ route('vendas.index') }}" class="btn btn-light px-5 py-2" style="min-width: 300px;">
            <i class="feather-arrow-left me-2"></i>
            <span>Voltar</span>
        </a>
    </div>
@endsection
