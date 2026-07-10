@extends('layouts.app')

@section('titulo', 'Meu Perfil - Meu Negocio')
@section('titulo-pagina', 'Meu Perfil')
@section('breadcrumb')
    <li class="breadcrumb-item active">Meu Perfil</li>
@endsection

@section('content')
    @php
        // Mantem a aba "Alterar senha" aberta apos erro de validacao especifico
        // de senha, e a aba "Dados pessoais" no caso default ou em erro de nome/email.
        $abaSenha = $errors->hasAny(['senha_atual', 'password']);

        // Voltar: lista de usuarios (se tem permissao) ou dashboard.
        $rotaVoltar = auth()->user()->can('usuario.ver') ? route('usuarios.index') : route('dashboard');
    @endphp

    <div class="row">
        {{-- Coluna esquerda: identificacao read-only --}}
        <div class="col-xxl-4 col-xl-5">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="mb-4 text-center">
                        <div class="wd-80 ht-80 mx-auto mb-3">
                            @if($usuario->imagem_url)
                                <img src="{{ $usuario->imagem_url }}" alt="{{ $usuario->nome }}" class="wd-80 ht-80 rounded-circle" style="object-fit:cover;">
                            @else
                                <div class="avatar-text avatar-xl bg-primary text-white rounded-circle fs-24 fw-bold">
                                    {{ mb_substr($usuario->nome, 0, 1) }}
                                </div>
                            @endif
                        </div>
                        <h5 class="fw-bold mb-1">{{ $usuario->nome }}</h5>
                        <span class="fs-12 text-muted">{{ $usuario->email }}</span>
                    </div>

                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-shield"></i>Perfil
                            </span>
                            <span>{{ $usuario->getRoleNames()->first() ?? '-' }}</span>
                        </li>
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-home"></i>Empresa default
                            </span>
                            <span>{{ $usuario->empresa?->nome ?? '-' }}</span>
                        </li>
                        <li class="mb-4">
                            <span class="text-muted fw-medium hstack gap-3 mb-2">
                                <i class="feather-grid"></i>Empresas com acesso
                            </span>
                            <span class="fs-13 d-block">
                                @if($usuario->hasRole('Admin'))
                                    <span class="badge bg-soft-primary text-primary">Admin acessa todas</span>
                                @elseif($usuario->empresas->isEmpty())
                                    -
                                @else
                                    @foreach($usuario->empresas as $emp)
                                        <span class="badge bg-soft-secondary text-secondary me-1 mb-1">{{ $emp->nome }}</span>
                                    @endforeach
                                @endif
                            </span>
                        </li>
                        <li class="hstack justify-content-between mb-0">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-calendar"></i>Cadastrado em
                            </span>
                            <span>{{ $usuario->created_at?->format('d/m/Y') ?? '-' }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Coluna direita: formularios em abas --}}
        <div class="col-xxl-8 col-xl-7">
            <div class="card border-top-0">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center" id="perfilTabs" role="tablist">
                        <li class="nav-item flex-fill border-top" role="presentation">
                            <a href="javascript:void(0);" class="nav-link {{ $abaSenha ? '' : 'active' }}" data-bs-toggle="tab" data-bs-target="#dadosTab" role="tab">
                                <i class="feather-user me-2"></i>Dados pessoais
                            </a>
                        </li>
                        <li class="nav-item flex-fill border-top" role="presentation">
                            <a href="javascript:void(0);" class="nav-link {{ $abaSenha ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#senhaTab" role="tab">
                                <i class="feather-lock me-2"></i>Alterar senha
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="tab-content">
                    {{-- Aba Dados pessoais --}}
                    <div class="tab-pane fade {{ $abaSenha ? '' : 'show active' }} p-4" id="dadosTab" role="tabpanel">
                        <form action="{{ route('perfil.atualizar') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <x-campo-imagem :atual="$usuario->imagem_thumb_url" label="Foto" />
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                                    <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $usuario->nome) }}" maxlength="200" required>
                                    @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $usuario->email) }}" required>
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mb-3 pb-3 pt-3">
                                <button type="submit" class="btn btn-primary px-5" style="min-width: 200px;">
                                    <i class="feather-save me-2"></i>Salvar
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- Aba Alterar senha --}}
                    <div class="tab-pane fade {{ $abaSenha ? 'show active' : '' }} p-4" id="senhaTab" role="tabpanel">
                        <form action="{{ route('perfil.senha') }}" method="POST">
                            @csrf
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label class="form-label">Senha atual <span class="text-danger">*</span></label>
                                    <input type="password" name="senha_atual" class="form-control @error('senha_atual') is-invalid @enderror" required>
                                    @error('senha_atual') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Nova senha <span class="text-danger">*</span></label>
                                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" minlength="8" required>
                                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <small class="text-muted">Minimo 8 caracteres.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirmar nova senha <span class="text-danger">*</span></label>
                                    <input type="password" name="password_confirmation" class="form-control" minlength="8" required>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mb-3 pb-3 pt-3">
                                <button type="submit" class="btn btn-primary px-5" style="min-width: 200px;">
                                    <i class="feather-lock me-2"></i>Alterar senha
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Voltar: fora dos cards, abaixo, padrao das telas show/edit --}}
    <div class="d-flex justify-content-start mt-4">
        <a href="{{ $rotaVoltar }}" class="btn btn-light px-5 py-2" style="min-width: 300px;">
            <i class="feather-arrow-left me-2"></i>Voltar
        </a>
    </div>
@endsection
