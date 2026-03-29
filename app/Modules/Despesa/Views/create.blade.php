@extends('layouts.app')

@section('titulo', 'Nova Despesa - Meu Negócio')
@section('titulo-pagina', 'Nova Despesa')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('despesas.index') }}">Despesas</a></li>
    <li class="breadcrumb-item active">Nova</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Cadastrar Despesa</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('despesas.store') }}" method="POST">
                @csrf
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" required>
                        @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor (R$) <span class="text-danger">*</span></label>
                        <input type="number" name="valor" class="form-control @error('valor') is-invalid @enderror" value="{{ old('valor') }}" step="0.01" min="0" required>
                        @error('valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Data <span class="text-danger">*</span></label>
                        <input type="date" name="data" class="form-control @error('data') is-invalid @enderror" value="{{ old('data', date('Y-m-d')) }}" required>
                        @error('data') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="{{ route('despesas.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
