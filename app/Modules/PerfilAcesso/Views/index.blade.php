@extends('layouts.app')

@section('titulo', 'Perfis de Acesso - Meu Negócio')
@section('titulo-pagina', 'Perfis de Acesso')
@section('breadcrumb')
    <li class="breadcrumb-item active">Perfis de Acesso</li>
@endsection

@section('content')
    @can('papel.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('perfis-acesso.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Novo Perfil de Acesso
            </a>
        </div>
    </div>
    @endcan

    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Permissões</th>
                            <th>Usuários</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($perfis as $perfil)
                        <tr>
                            <td>
                                {{ $perfil->name }}
                                @if($perfil->name === 'Admin')
                                <span class="badge bg-primary ms-1">Sistema</span>
                                @endif
                            </td>
                            <td>{{ $perfil->permissions->count() }}</td>
                            <td>{{ $perfil->users()->count() }}</td>
                            <td>
                                <div class="hstack gap-2 justify-content-end">
                                    <div class="dropdown">
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            @can('papel.editar')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('perfis-acesso.edit', $perfil) }}">
                                                    <i class="feather-edit-3 me-3"></i>
                                                    <span>Editar</span>
                                                </a>
                                            </li>
                                            @endcan
                                            @can('papel.excluir')
                                            @if($perfil->name !== 'Admin')
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('perfis-acesso.destroy', $perfil) }}" method="POST" data-confirm="Excluir este perfil de acesso?">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="feather-trash-2 me-3"></i>
                                                        <span>Excluir</span>
                                                    </button>
                                                </form>
                                            </li>
                                            @endif
                                            @endcan
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Nenhum perfil de acesso cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
