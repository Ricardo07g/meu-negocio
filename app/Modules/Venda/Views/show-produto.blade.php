@extends('layouts.app')

@section('titulo', 'Venda de Produto - Meu Negócio')
@section('titulo-pagina', 'Detalhes da Venda')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('vendas.index') }}">Vendas</a></li>
    <li class="breadcrumb-item active">Detalhes</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Venda de Produto</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3"><strong>Produto:</strong> {{ $vendaProduto->produto->nome }}</div>
                <div class="col-md-6 mb-3"><strong>Cliente:</strong> {{ $vendaProduto->cliente->nome ?? '-' }}</div>
                <div class="col-md-6 mb-3"><strong>Quantidade:</strong> {{ $vendaProduto->quantidade }}</div>
                <div class="col-md-6 mb-3"><strong>Valor Total:</strong> R$ {{ number_format($vendaProduto->valor_total, 2, ',', '.') }}</div>
                <div class="col-md-6 mb-3"><strong>Data:</strong> {{ $vendaProduto->created_at->format('d/m/Y H:i') }}</div>
            </div>
        </div>
    </div>
@endsection
