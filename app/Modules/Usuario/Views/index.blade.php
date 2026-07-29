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
                <table class="table table-hover tabela-empilha mb-0">
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
                            <td data-label="Nome">{{ $usuario->nome }}</td>
                            <td data-label="Email">{{ $usuario->email }}</td>
                            <td data-label="Status">
                                @if($usuario->ativo)
                                    <span class="badge bg-soft-success text-success">Ativo</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary">Inativo</span>
                                @endif
                            </td>
                            <td data-label="Perfil de Acesso">{{ $usuario->getRoleNames()->first() ?? '-' }}</td>
                            <td>
                                <x-acoes-linha :editar="route('usuarios.edit', $usuario)"
                                               permissaoEditar="usuario.editar"
                                               :excluir="route('usuarios.destroy', $usuario)"
                                               permissaoExcluir="usuario.excluir"
                                               confirmacao="Excluir este usuário?" />
                            </td>
                        </tr>
                        @empty
                        <tr class="sem-registros"><td colspan="6" class="text-center text-muted py-4">Nenhum usuário cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <x-paginacao :paginator="$usuarios" />
    </div>
@endsection
