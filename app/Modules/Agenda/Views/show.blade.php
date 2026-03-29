@extends('layouts.app')

@section('titulo', 'Agendamento - Meu Negócio')
@section('titulo-pagina', 'Detalhes do Agendamento')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('agenda.index') }}">Agenda</a></li>
    <li class="breadcrumb-item active">Detalhes</li>
@endsection

@section('content')
    {{-- Botões no topo --}}
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6 d-flex gap-2">
            @can('agendamento.editar')
            @if(!in_array($agendamento->status->value, ['cancelado', 'finalizado']))
            <a href="{{ route('agenda.edit', $agendamento) }}" class="btn btn-primary">
                <i class="feather-edit me-2"></i>Editar
            </a>
            @endif
            @endcan
            <a href="{{ route('agenda.index', ['data' => $agendamento->inicio->format('Y-m-d')]) }}" class="btn btn-light">
                <i class="feather-arrow-left me-2"></i>Voltar
            </a>
        </div>
    </div>

    <div class="card stretch stretch-full">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3"><strong>Cliente:</strong> {{ $agendamento->cliente->nome ?? '-' }}</div>
                <div class="col-md-6 mb-3"><strong>Serviço:</strong> {{ $agendamento->servico->nome ?? '-' }}</div>
                <div class="col-md-6 mb-3"><strong>Atendente:</strong> {{ $agendamento->atendente->nome ?? '-' }}</div>
                <div class="col-md-6 mb-3"><strong>Data:</strong> {{ $agendamento->inicio->format('d/m/Y') }}</div>
                <div class="col-md-6 mb-3"><strong>Horário:</strong> {{ $agendamento->inicio->format('H:i') }} - {{ $agendamento->fim->format('H:i') }}</div>
                <div class="col-md-6 mb-3">
                    <strong>Status:</strong>
                    @switch($agendamento->status->value)
                        @case('agendado') <span class="badge bg-info">Agendado</span> @break
                        @case('confirmado') <span class="badge bg-primary">Confirmado</span> @break
                        @case('finalizado') <span class="badge bg-success">Finalizado</span> @break
                        @case('cancelado') <span class="badge bg-danger">Cancelado</span> @break
                        @default <span class="badge bg-secondary">{{ ucfirst($agendamento->status->value) }}</span>
                    @endswitch
                </div>
                @if($agendamento->observacoes)
                <div class="col-12 mb-3"><strong>Observações:</strong> {{ $agendamento->observacoes }}</div>
                @endif
                @if($agendamento->vendaPacote)
                <div class="col-md-6 mb-3">
                    <strong>Pacote:</strong>
                    <a href="{{ route('vendas.show-pacote', $agendamento->vendaPacote) }}">#{{ $agendamento->vendaPacote->id }}</a>
                </div>
                @endif
            </div>
        </div>
    </div>
@endsection
