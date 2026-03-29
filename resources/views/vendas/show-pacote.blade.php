@extends('layouts.app')

@section('titulo', 'Pacote - Meu Negócio')
@section('titulo-pagina', 'Detalhes do Pacote')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('vendas.index') }}">Vendas</a></li>
    <li class="breadcrumb-item active">Detalhes</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title">Pacote #{{ $pacote->id }}</h5>
            @if($pacote->status->value === 'ativo')
            @can('agendamento.cancelar')
            <form action="{{ route('vendas.cancelar-pacote', $pacote) }}" method="POST" data-confirm="Cancelar este pacote e todos agendamentos pendentes?">
                @csrf @method('PATCH')
                <button class="btn btn-sm btn-danger"><i class="feather-x-circle me-1"></i> Cancelar Pacote</button>
            </form>
            @endcan
            @endif
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-4 mb-3"><strong>Cliente:</strong> {{ $pacote->cliente->nome }}</div>
                <div class="col-md-4 mb-3"><strong>Serviço:</strong> {{ $pacote->servico->nome }}</div>
                <div class="col-md-4 mb-3"><strong>Profissional:</strong> {{ $pacote->profissional->usuario->nome }}</div>
                <div class="col-md-4 mb-3"><strong>Valor Total:</strong> R$ {{ number_format($pacote->valor_total, 2, ',', '.') }}</div>
                <div class="col-md-4 mb-3"><strong>Valor/Sessão:</strong> R$ {{ number_format($pacote->valor_total / $pacote->qtd_sessoes, 2, ',', '.') }}</div>
                <div class="col-md-4 mb-3">
                    <strong>Status:</strong>
                    @switch($pacote->status->value)
                        @case('ativo') <span class="badge bg-success">Ativo</span> @break
                        @case('concluido') <span class="badge bg-primary">Concluído</span> @break
                        @case('cancelado') <span class="badge bg-danger">Cancelado</span> @break
                    @endswitch
                </div>
                <div class="col-md-4 mb-3"><strong>Sessões:</strong> {{ $pacote->sessoesRealizadas() }} realizadas / {{ $pacote->qtd_sessoes }} total</div>
                <div class="col-md-4 mb-3"><strong>Pendentes:</strong> {{ $pacote->sessoesPendentes() }}</div>
            </div>
        </div>
    </div>

    <div class="card stretch stretch-full mt-4">
        <div class="card-header">
            <h5 class="card-title">Sessões (Agendamentos)</h5>
        </div>
        <div class="card-body custom-card-action">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Obs.</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pacote->agendamentos->sortBy('inicio') as $index => $ag)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $ag->inicio->format('d/m/Y') }}</td>
                            <td>{{ $ag->inicio->format('H:i') }} - {{ $ag->fim->format('H:i') }}</td>
                            <td class="text-muted fs-12">{{ $ag->observacoes ?? '-' }}</td>
                            <td>
                                @switch($ag->status->value)
                                    @case('agendado') <span class="badge bg-info">Agendado</span> @break
                                    @case('confirmado') <span class="badge bg-primary">Confirmado</span> @break
                                    @case('finalizado') <span class="badge bg-success">Finalizado</span> @break
                                    @case('cancelado') <span class="badge bg-danger">Cancelado</span> @break
                                @endswitch
                            </td>
                            <td class="text-end">
                                @if(!in_array($ag->status->value, ['cancelado', 'finalizado']))
                                <a href="{{ route('agenda.edit', $ag) }}" class="btn btn-sm btn-light-brand"><i class="feather-edit-3"></i></a>
                                @endif
                                <a href="{{ route('agenda.show', $ag) }}" class="btn btn-sm btn-light"><i class="feather-eye"></i></a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
