@extends('layouts.app')

@section('titulo', 'Novo Produto - Meu Negócio')
@section('titulo-pagina', 'Novo Produto')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('produtos.index') }}">Produtos</a></li>
    <li class="breadcrumb-item active">Novo</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Cadastrar Produto</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('produtos.store') }}" method="POST">
                @csrf
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" required>
                        @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Quantidade <span class="text-danger">*</span></label>
                        <input type="number" name="quantidade" class="form-control @error('quantidade') is-invalid @enderror" value="{{ old('quantidade', 0) }}" min="0" required>
                        @error('quantidade') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor (R$) <span class="text-danger">*</span></label>
                        <input type="number" name="valor" class="form-control @error('valor') is-invalid @enderror" value="{{ old('valor') }}" step="0.01" min="0" required>
                        @error('valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="{{ route('produtos.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
