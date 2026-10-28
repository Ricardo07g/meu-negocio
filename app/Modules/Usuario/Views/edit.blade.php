@extends('layouts.app')

@section('titulo', 'Editar Usuário - Meu Negócio')
@section('titulo-pagina', 'Editar Usuário')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('usuarios.index') }}">Usuários</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <form action="{{ route('usuarios.update', $usuario) }}" method="POST" enctype="multipart/form-data">
        @csrf @method('PUT')

        @include('usuario::_form', ['entidade' => $usuario])

        <x-form-botoes :voltar="route('usuarios.index')" />
    </form>

    {{-- Fora do form de edicao: forms nao se aninham (ADR-0022) --}}
    @if($usuario->temDoisFatoresAtivo() && $usuario->hasRole('Admin') && auth()->user()->hasRole('Admin') && auth()->id() !== $usuario->id)
        {{-- 2FA de Admin so o suporte redefine (UsuarioPolicy::redefinirDoisFatores). --}}
        <div class="alert alert-light mt-4 mb-0" role="alert">
            <i class="feather-shield me-2"></i>Verificação em duas etapas ativa. Se este administrador perdeu o acesso ao aplicativo, fale com o suporte.
        </div>
    @endif

    @if($usuario->temDoisFatoresAtivo())
        @can('redefinirDoisFatores', $usuario)
            <div class="card mt-4">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <p class="fw-bold mb-1"><i class="feather-shield me-2"></i>Verificação em duas etapas ativa</p>
                        <p class="fs-12 text-muted mb-0">Se o usuário perdeu o celular e os códigos de recuperação, redefina para ele configurar de novo.</p>
                    </div>
                    <form action="{{ route('usuarios.redefinir-2fa', $usuario) }}" method="POST"
                          data-confirm="Redefinir a verificação em duas etapas deste usuário? Ele entrará só com a senha até configurar de novo.">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger"><i class="feather-rotate-ccw me-2"></i>Redefinir 2FA</button>
                    </form>
                </div>
            </div>
        @endcan
    @endif
@endsection
