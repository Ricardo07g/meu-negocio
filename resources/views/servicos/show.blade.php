@extends('layouts.app')

@section('titulo', 'Serviço - Meu Negócio')
@section('titulo-pagina', $servico->nome)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('servicos.index') }}">Serviços</a></li>
    <li class="breadcrumb-item active">{{ $servico->nome }}</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title">Dados do Serviço</h5>
            @can('servico.editar')
            <a href="{{ route('servicos.edit', $servico) }}" class="btn btn-sm btn-primary"><i class="feather-edit me-1"></i> Editar</a>
            @endcan
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3"><strong>Nome:</strong> {{ $servico->nome }}</div>
                <div class="col-md-4 mb-3"><strong>Duração:</strong> {{ $servico->duracao }} minutos</div>
                <div class="col-md-4 mb-3"><strong>Valor:</strong> R$ {{ number_format($servico->valor, 2, ',', '.') }}</div>
            </div>
        </div>
    </div>
@endsection
