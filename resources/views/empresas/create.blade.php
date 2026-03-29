@extends('layouts.app')

@section('titulo', 'Nova Empresa - Meu Negócio')
@section('titulo-pagina', 'Nova Empresa')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('empresas.index') }}">Empresas</a></li>
    <li class="breadcrumb-item active">Nova</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Cadastrar Empresa</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('empresas.store') }}" method="POST">
                @csrf
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" required>
                        @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Documento</label>
                        <input type="text" name="documento" class="form-control @error('documento') is-invalid @enderror" value="{{ old('documento') }}">
                        @error('documento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" class="form-control @error('telefone') is-invalid @enderror" value="{{ old('telefone') }}">
                        @error('telefone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="{{ route('empresas.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
