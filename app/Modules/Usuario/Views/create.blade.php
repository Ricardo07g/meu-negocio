@extends('layouts.app')

@section('titulo', 'Novo Usuário - Meu Negócio')
@section('titulo-pagina', 'Novo Usuário')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('usuarios.index') }}">Usuários</a></li>
    <li class="breadcrumb-item active">Novo</li>
@endsection

@section('content')
    <form action="{{ route('usuarios.store') }}" method="POST">
        @csrf
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Cadastrar Usuário</h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" required>
                        @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Senha <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Papel <span class="text-danger">*</span></label>
                        <select name="papel" class="form-select @error('papel') is-invalid @enderror" required>
                            <option value="">Selecione...</option>
                            @foreach($papeis as $papel)
                                <option value="{{ $papel }}" {{ old('papel') == $papel ? 'selected' : '' }}>{{ $papel }}</option>
                            @endforeach
                        </select>
                        @error('papel') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="hidden" name="atende" value="0">
                            <input type="checkbox" name="atende" value="1" class="form-check-input" id="atende" {{ old('atende') ? 'checked' : '' }}>
                            <label class="form-check-label" for="atende">Este usuário realiza atendimentos</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-form-botoes :voltar="route('usuarios.index')" />
    </form>
@endsection
