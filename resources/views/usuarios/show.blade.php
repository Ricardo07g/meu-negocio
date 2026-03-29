@extends('layouts.app')

@section('titulo', 'Usuário - Meu Negócio')
@section('titulo-pagina', $usuario->nome)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('usuarios.index') }}">Usuários</a></li>
    <li class="breadcrumb-item active">{{ $usuario->nome }}</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title">Dados do Usuário</h5>
            @can('usuario.editar')
            <a href="{{ route('usuarios.edit', $usuario) }}" class="btn btn-sm btn-primary"><i class="feather-edit me-1"></i> Editar</a>
            @endcan
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3"><strong>Nome:</strong> {{ $usuario->nome }}</div>
                <div class="col-md-6 mb-3"><strong>Email:</strong> {{ $usuario->email }}</div>
                <div class="col-md-6 mb-3"><strong>Papel:</strong> {{ ucfirst($usuario->papel) }}</div>
                <div class="col-md-6 mb-3">
                    <strong>Ativo:</strong>
                    @if($usuario->ativo)
                        <span class="badge bg-success">Sim</span>
                    @else
                        <span class="badge bg-danger">Não</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
