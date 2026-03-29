@extends('layouts.app')

@section('titulo', 'Empresa - Meu Negócio')
@section('titulo-pagina', $empresa->nome)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('empresas.index') }}">Empresas</a></li>
    <li class="breadcrumb-item active">{{ $empresa->nome }}</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title">Dados da Empresa</h5>
            @can('empresa.editar')
            <a href="{{ route('empresas.edit', $empresa) }}" class="btn btn-sm btn-primary"><i class="feather-edit me-1"></i> Editar</a>
            @endcan
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
@endsection
