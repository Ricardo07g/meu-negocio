@extends('layouts.app')

@section('titulo', 'Perfil de Acesso - Meu Negócio')
@section('titulo-pagina', $perfilAcesso->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('perfis-acesso.index') }}">Perfis de Acesso</a></li>
    <li class="breadcrumb-item active">{{ $perfilAcesso->name }}</li>
@endsection

@section('content')
    @php
        $ehAdmin = $perfilAcesso->name === 'Admin';
        $permissoesAgrupadas = $perfilAcesso->permissions
            ->groupBy(fn ($p) => explode('.', $p->name)[0])
            ->sortKeys();
    @endphp

    {{-- Cabeçalho do perfil --}}
    <div class="card stretch stretch-full mb-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-text avatar-lg bg-soft-primary text-primary rounded">
                    <i class="feather-shield"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h4 class="fw-bold mb-0">{{ $perfilAcesso->name }}</h4>
                        @if($ehAdmin)
                            <span class="badge bg-primary">Sistema</span>
                        @endif
                    </div>
                    <p class="fs-12 text-muted mb-0">
                        @if($ehAdmin)
                            Perfil do sistema — somente leitura.
                        @else
                            Perfil de acesso e suas permissões.
                        @endif
                    </p>
                </div>
            </div>
            @can('update', $perfilAcesso)
                <a href="{{ route('perfis-acesso.edit', $perfilAcesso) }}" class="btn btn-primary">
                    <i class="feather-edit-3 me-2"></i>Editar
                </a>
            @endcan
        </div>
    </div>

    {{-- Indicadores --}}
    <div class="row mb-2">
        <div class="col-sm-6 mb-4">
            <div class="card stretch stretch-full">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="avatar-text avatar-lg bg-soft-primary text-primary rounded">
                        <i class="feather-users"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0">{{ $perfilAcesso->users()->count() }}</h4>
                        <span class="fs-12 text-muted">Usuários vinculados</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 mb-4">
            <div class="card stretch stretch-full">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="avatar-text avatar-lg bg-soft-success text-success rounded">
                        <i class="feather-key"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0">{{ $perfilAcesso->permissions->count() }}</h4>
                        <span class="fs-12 text-muted">Permissões concedidas</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Permissões agrupadas por módulo --}}
    @if($permissoesAgrupadas->isEmpty())
        <div class="card stretch stretch-full">
            <div class="card-body text-center text-muted py-5">
                <i class="feather-lock d-block mb-2" style="font-size: 2rem;"></i>
                Este perfil de acesso não possui permissões atribuídas.
            </div>
        </div>
    @else
        <div class="row">
            @foreach($permissoesAgrupadas as $modulo => $perms)
                <div class="col-md-6 col-xl-4 mb-4">
                    <div class="card stretch stretch-full h-100">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h6 class="fw-bold mb-0 text-truncate">{{ ucfirst(str_replace('_', ' ', $modulo)) }}</h6>
                            <span class="badge bg-soft-primary text-primary">{{ $perms->count() }}</span>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($perms as $perm)
                                    <span class="badge bg-soft-success text-success text-capitalize">
                                        {{ str_replace('_', ' ', explode('.', $perm->name)[1] ?? $perm->name) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="d-flex pt-4">
        <a href="{{ route('perfis-acesso.index') }}" class="btn btn-light">
            <i class="feather-arrow-left me-2"></i>
            <span>Voltar</span>
        </a>
    </div>
@endsection
