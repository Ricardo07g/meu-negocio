@extends('layouts.app')

@section('titulo', 'Editar Despesa - Meu Negócio')
@section('titulo-pagina', 'Editar Despesa')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('despesas.index') }}">Despesas</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Editar Despesa</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('despesas.update', $despesa) }}" method="POST">
                @csrf @method('PUT')
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $despesa->nome) }}" required>
                        @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor (R$) <span class="text-danger">*</span></label>
                        <input type="number" name="valor" class="form-control @error('valor') is-invalid @enderror" value="{{ old('valor', $despesa->valor) }}" step="0.01" min="0" required>
                        @error('valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Data <span class="text-danger">*</span></label>
                        <input type="date" name="data" class="form-control @error('data') is-invalid @enderror" value="{{ old('data', $despesa->data instanceof \Carbon\Carbon ? $despesa->data->format('Y-m-d') : $despesa->data) }}" required>
                        @error('data') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Atualizar</button>
                    <a href="{{ route('despesas.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
