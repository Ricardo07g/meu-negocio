@extends('layouts.app')

@section('titulo', 'Usuários - Meu Negócio')
@section('titulo-pagina', 'Usuários')
@section('breadcrumb')
    <li class="breadcrumb-item active">Usuários</li>
@endsection

@section('content')
    @include('partials.aviso-limite-plano', [
        'recurso' => 'usuarios',
        'atual' => $limite['atual'],
        'maximo' => $limite['maximo'],
        'atingido' => $limite['atingido'],
        'rotaCriar' => route('usuarios.create'),
        'labelBotao' => 'Novo Usuário',
        'permissaoBlade' => 'usuario.criar',
    ])

    {{-- Card with table --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:56px"></th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Perfil de Acesso</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($usuarios as $usuario)
                        <tr>
                            <td><x-thumb :url="$usuario->imagem_thumb_url" :nome="$usuario->nome" /></td>
                            <td>{{ $usuario->nome }}</td>
                            <td>{{ $usuario->email }}</td>
                            <td>
                                @if($usuario->ativo)
                                    <span class="badge bg-soft-success text-success">Ativo</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary">Inativo</span>
                                @endif
                            </td>
                            <td>{{ $usuario->getRoleNames()->first() ?? '-' }}</td>
                            <td>
                                <div class="hstack gap-2 justify-content-end">
                                    <div class="dropdown">
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            @can('usuario.editar')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('usuarios.edit', $usuario) }}">
                                                    <i class="feather-edit-3 me-3"></i>
                                                    <span>Editar</span>
                                                </a>
                                            </li>
                                            @endcan
                                            @can('usuario.excluir')
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('usuarios.destroy', $usuario) }}" method="POST" data-confirm="Excluir este usuário?">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="feather-trash-2 me-3"></i>
                                                        <span>Excluir</span>
                                                    </button>
                                                </form>
                                            </li>
                                            @endcan
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum usuário cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($usuarios->hasPages())
            <div class="card-footer">
                {{ $usuarios->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
