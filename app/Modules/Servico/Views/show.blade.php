@extends('layouts.app')

@section('titulo', 'Serviço - Meu Negócio')
@section('titulo-pagina', $servico->nome)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('servicos.index') }}">Serviços</a></li>
    <li class="breadcrumb-item active">{{ $servico->nome }}</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Dados do Serviço</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3"><strong>Nome:</strong> {{ $servico->nome }}</div>
                <div class="col-md-4 mb-3"><strong>Duração:</strong> {{ $servico->duracao }} minutos</div>
                <div class="col-md-4 mb-3"><strong>Valor:</strong> R$ {{ number_format($servico->valor, 2, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 text-center pt-4">
        <a href="{{ route('servicos.index') }}" class="w-50 btn btn-light">
            <i class="feather-arrow-left me-2"></i>
            <span>Voltar</span>
        </a>
        @can('servico.editar')
        <a href="{{ route('servicos.edit', $servico) }}" class="w-50 btn btn-primary">
            <i class="feather-edit me-2"></i>
            <span>Editar</span>
        </a>
        @endcan
    </div>
@endsection
