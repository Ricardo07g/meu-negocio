@extends('layouts.app')

@section('titulo', 'Novo Papel - Meu Negócio')
@section('titulo-pagina', 'Novo Papel')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('papeis.index') }}">Papéis</a></li>
    <li class="breadcrumb-item active">Novo</li>
@endsection

@section('content')
    <form action="{{ route('papeis.store') }}" method="POST">
        @csrf
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Cadastrar Papel</h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Nome do Papel <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <h6 class="fw-bold mb-3">Permissões</h6>

                @foreach($permissoes as $modulo => $perms)
                <div class="mb-4">
                    <label class="form-label fw-semibold text-uppercase fs-11 text-muted">{{ $modulo }}</label>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach($perms as $perm)
                        <div class="form-check">
                            <input type="checkbox" name="permissoes[]" value="{{ $perm->name }}" class="form-check-input"
                                id="perm_{{ $perm->id }}" {{ in_array($perm->name, old('permissoes', [])) ? 'checked' : '' }}>
                            <label class="form-check-label" for="perm_{{ $perm->id }}">{{ explode('.', $perm->name)[1] }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="{{ route('papeis.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </div>
        </div>
    </form>
@endsection
