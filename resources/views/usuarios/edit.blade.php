@extends('layouts.app')

@section('titulo', 'Editar Usuário - Meu Negócio')
@section('titulo-pagina', 'Editar Usuário')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('usuarios.index') }}">Usuários</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Editar Usuário</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('usuarios.update', $usuario) }}" method="POST">
                @csrf @method('PUT')
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $usuario->nome) }}" required>
                        @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $usuario->email) }}" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Senha <small class="text-muted">(deixe em branco para manter a atual)</small></label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Papel <span class="text-danger">*</span></label>
                        <select name="papel" class="form-select @error('papel') is-invalid @enderror" required>
                            <option value="">Selecione...</option>
                            @foreach($papeis as $papel)
                                <option value="{{ $papel }}" {{ old('papel', $usuario->getRoleNames()->first()) == $papel ? 'selected' : '' }}>{{ $papel }}</option>
                            @endforeach
                        </select>
                        @error('papel') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Atualizar</button>
                    <a href="{{ route('usuarios.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
