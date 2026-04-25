@extends('layouts.app')

@section('titulo', 'Perfil de Acesso - Meu Negócio')
@section('titulo-pagina', $perfilAcesso->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('perfis-acesso.index') }}">Perfis de Acesso</a></li>
    <li class="breadcrumb-item active">{{ $perfilAcesso->name }}</li>
@endsection

@section('content')
    @php
        $permissoesAgrupadas = $perfilAcesso->permissions->groupBy(fn ($p) => explode('.', $p->name)[0]);
    @endphp

    <div class="card stretch stretch-full">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">{{ $perfilAcesso->name }}</h5>
            @can('update', $perfilAcesso)
                <a href="{{ route('perfis-acesso.edit', $perfilAcesso) }}" class="btn btn-primary">
                    <i data-feather="edit-2" class="me-1"></i> Editar
                </a>
            @endcan
        </div>
        <div class="card-body">
            <dl class="row mb-4">
                <dt class="col-sm-3">Usuários vinculados</dt>
                <dd class="col-sm-9">{{ $perfilAcesso->users()->count() }}</dd>

                <dt class="col-sm-3">Total de permissões</dt>
                <dd class="col-sm-9">{{ $perfilAcesso->permissions->count() }}</dd>
            </dl>

            <h6 class="fw-bold mb-3">Permissões concedidas</h6>

            @forelse($permissoesAgrupadas as $modulo => $perms)
                <div class="mb-3">
                    <label class="form-label fw-semibold text-uppercase fs-11 text-muted">{{ $modulo }}</label>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($perms as $perm)
                            <span class="badge bg-success">{{ explode('.', $perm->name)[1] ?? $perm->name }}</span>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-muted">Este perfil de acesso não possui permissões atribuídas.</p>
            @endforelse
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">
        <a href="{{ route('perfis-acesso.index') }}" class="btn btn-light px-5 py-2" style="min-width: 300px;">Voltar</a>
    </div>
@endsection
