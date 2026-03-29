@extends('layouts.app')

@section('titulo', 'Novo Movimento de Estoque - Meu Negócio')
@section('titulo-pagina', 'Novo Movimento de Estoque')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('movimentos-estoque.index') }}">Movimentos de Estoque</a></li>
    <li class="breadcrumb-item active">Novo</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Registrar Movimento de Estoque</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('movimentos-estoque.store') }}" method="POST">
                @csrf
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Produto <span class="text-danger">*</span></label>
                        <select name="produto_id" class="form-select @error('produto_id') is-invalid @enderror" required>
                            <option value="">Selecione...</option>
                            @foreach($produtos as $produto)
                                <option value="{{ $produto->id }}" {{ old('produto_id', request('produto_id')) == $produto->id ? 'selected' : '' }}>{{ $produto->nome }}</option>
                            @endforeach
                        </select>
                        @error('produto_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select name="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                            <option value="">Selecione...</option>
                            @foreach(['entrada' => 'Entrada', 'saida' => 'Saída', 'ajuste' => 'Ajuste'] as $valor => $label)
                                <option value="{{ $valor }}" {{ old('tipo') == $valor ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Quantidade <span class="text-danger">*</span></label>
                        <input type="number" name="quantidade" class="form-control @error('quantidade') is-invalid @enderror" value="{{ old('quantidade') }}" min="1" required>
                        @error('quantidade') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="{{ route('movimentos-estoque.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
