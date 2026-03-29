@extends('layouts.app')

@section('titulo', 'Pagamento - Meu Negócio')
@section('titulo-pagina', 'Detalhes do Pagamento')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('pagamentos.index') }}">Pagamentos</a></li>
    <li class="breadcrumb-item active">Detalhes</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Dados do Pagamento</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3"><strong>Valor:</strong> R$ {{ number_format($pagamento->valor, 2, ',', '.') }}</div>
                <div class="col-md-6 mb-3"><strong>Forma de Pagamento:</strong> {{ ucfirst($pagamento->forma_pagamento->value) }}</div>
                <div class="col-md-6 mb-3">
                    <strong>Status:</strong>
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
                        @default
                            <span class="badge bg-secondary">{{ ucfirst($pagamento->status->value) }}</span>
                    @endswitch
                </div>
                <div class="col-md-6 mb-3"><strong>Cliente:</strong> {{ $pagamento->agendamento->cliente->nome ?? '-' }}</div>
                @if($pagamento->agendamento)
                <div class="col-md-6 mb-3"><strong>Agendamento:</strong> #{{ $pagamento->agendamento->id }} - {{ \Carbon\Carbon::parse($pagamento->agendamento->inicio)->format('d/m/Y H:i') }}</div>
                <div class="col-md-6 mb-3"><strong>Serviço:</strong> {{ $pagamento->agendamento->servico->nome ?? '-' }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
