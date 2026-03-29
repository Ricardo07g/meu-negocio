@extends('layouts.app')

@section('titulo', 'Editar Cliente - Meu Negócio')
@section('titulo-pagina', 'Editar Cliente')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('clientes.index') }}">Clientes</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Editar Cliente</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('clientes.update', $cliente) }}" method="POST">
                @csrf @method('PUT')
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $cliente->nome) }}" required>
                        @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" class="form-control" value="{{ old('telefone', $cliente->telefone) }}">
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $cliente->email) }}">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Observações</label>
                    <textarea name="observacoes" class="form-control" rows="3">{{ old('observacoes', $cliente->observacoes) }}</textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Atualizar</button>
                    <a href="{{ route('clientes.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
