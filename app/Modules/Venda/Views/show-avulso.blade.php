@extends('layouts.app')

@section('titulo', 'Venda - Meu Negócio')
@section('titulo-pagina', 'Detalhes da Venda')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('vendas.index') }}">Vendas</a></li>
    <li class="breadcrumb-item active">Detalhes</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title">Venda Avulsa</h5>
            @if(!in_array($agendamento->status->value, ['cancelado', 'finalizado']))
            @can('agendamento.cancelar')
            <form action="{{ route('vendas.cancelar-avulso', $agendamento) }}" method="POST" data-confirm="Cancelar este agendamento?">
                @csrf @method('PATCH')
                <button class="btn btn-sm btn-danger"><i class="feather-x-circle me-1"></i> Cancelar</button>
            </form>
            @endcan
            @endif
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3"><strong>Cliente:</strong> {{ $agendamento->cliente->nome ?? '-' }}</div>
                <div class="col-md-6 mb-3"><strong>Serviço:</strong> {{ $agendamento->servico->nome ?? '-' }}</div>
                <div class="col-md-6 mb-3"><strong>Profissional:</strong> {{ $agendamento->profissional->usuario->nome ?? '-' }}</div>
                <div class="col-md-6 mb-3"><strong>Data/Hora:</strong> {{ $agendamento->inicio->format('d/m/Y H:i') }}</div>
                <div class="col-md-6 mb-3">
                    <strong>Status:</strong>
                    @switch($agendamento->status->value)
                        @case('agendado')
                            <span class="badge bg-info">Agendado</span>
                            @break
                        @case('confirmado')
                            <span class="badge bg-primary">Confirmado</span>
                            @break
                        @case('finalizado')
                            <span class="badge bg-success">Finalizado</span>
                            @break
                        @case('cancelado')
                            <span class="badge bg-danger">Cancelado</span>
                            @break
                        @default
                            <span class="badge bg-secondary">{{ ucfirst($agendamento->status->value) }}</span>
                    @endswitch
                </div>
            </div>
        </div>
    </div>
@endsection
