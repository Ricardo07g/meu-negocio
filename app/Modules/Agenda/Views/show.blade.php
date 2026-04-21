@extends('layouts.app')

@section('titulo', 'Agendamento - Meu Negócio')
@section('titulo-pagina', 'Detalhes do Agendamento')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('agenda.index') }}">Agenda</a></li>
    <li class="breadcrumb-item active">Detalhes</li>
@endsection

@section('content')
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
                    <strong>Pacote:</strong> #{{ $agendamento->vendaPacote->id }}
                </div>
                @endif
            </div>
        </div>
    </div>

    @php
        $podeEditar = auth()->user()->can('agendamento.editar')
            && !in_array($agendamento->status->value, ['cancelado', 'finalizado']);
    @endphp
    <div class="d-flex gap-2 text-center pt-4">
        <a href="{{ route('agenda.index', ['data' => $agendamento->inicio->format('Y-m-d')]) }}" class="w-50 btn btn-light">
            <i class="feather-arrow-left me-2"></i>
            <span>Voltar</span>
        </a>
        @if($podeEditar)
        <a href="{{ route('agenda.edit', $agendamento) }}" class="w-50 btn btn-primary">
            <i class="feather-edit me-2"></i>
            <span>Editar</span>
        </a>
        @endif
    </div>
@endsection
