@extends('layouts.app')

@section('titulo', 'Abrir Caixa - Meu Negócio')
@section('titulo-pagina', 'Abrir Caixa')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('caixas.index') }}">Caixa</a></li>
    <li class="breadcrumb-item active">Abrir</li>
@endsection

@section('content')
    <form action="{{ route('caixas.store') }}" method="POST">
        @csrf
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Abrir Caixa</h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Saldo de Abertura (R$) <span class="text-danger">*</span></label>
                        <input type="number" name="saldo_abertura" class="form-control @error('saldo_abertura') is-invalid @enderror" value="{{ old('saldo_abertura', '0.00') }}" step="0.01" min="0" required>
                        @error('saldo_abertura') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Observação</label>
                        <textarea name="observacao" class="form-control @error('observacao') is-invalid @enderror" rows="3">{{ old('observacao') }}</textarea>
                        @error('observacao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <x-form-botoes :voltar="route('caixas.index')" />
    </form>
@endsection
