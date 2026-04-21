@extends('layouts.app')

@section('titulo', 'Empresa - Meu Negócio')
@section('titulo-pagina', $empresa->nome)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('empresas.index') }}">Empresas</a></li>
    <li class="breadcrumb-item active">{{ $empresa->nome }}</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Dados da Empresa</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3"><strong>Nome:</strong> {{ $empresa->nome }}</div>
                <div class="col-md-6 mb-3"><strong>Documento:</strong> {{ $empresa->documento ?? '-' }}</div>
                <div class="col-md-6 mb-3"><strong>Telefone:</strong> {{ $empresa->telefone ?? '-' }}</div>
                <div class="col-md-6 mb-3"><strong>Email:</strong> {{ $empresa->email ?? '-' }}</div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 text-center pt-4">
        <a href="{{ route('empresas.index') }}" class="w-50 btn btn-light">
            <i class="feather-arrow-left me-2"></i>
            <span>Voltar</span>
        </a>
        @can('empresa.editar')
        <a href="{{ route('empresas.edit', $empresa) }}" class="w-50 btn btn-primary">
            <i class="feather-edit me-2"></i>
            <span>Editar</span>
        </a>
        @endcan
    </div>
@endsection
