@extends('layouts.app')

@section('titulo', 'Editar Produto - Meu Negócio')
@section('titulo-pagina', 'Editar Produto')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('produtos.index') }}">Produtos</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <form action="{{ route('produtos.update', $produto) }}" method="POST">
        @csrf @method('PUT')
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Editar Produto</h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $produto->nome) }}" required>
                        @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Quantidade <span class="text-danger">*</span></label>
                        <input type="number" name="quantidade" class="form-control @error('quantidade') is-invalid @enderror" value="{{ old('quantidade', $produto->quantidade) }}" min="0" required>
                        @error('quantidade') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor (R$) <span class="text-danger">*</span></label>
                        <input type="number" name="valor" class="form-control @error('valor') is-invalid @enderror" value="{{ old('valor', $produto->valor) }}" step="0.01" min="0" required>
                        @error('valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <x-form-botoes :voltar="route('produtos.index')" />
    </form>
@endsection
