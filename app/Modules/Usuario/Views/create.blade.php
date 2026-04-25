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
                        <label class="form-label">Perfil de Acesso <span class="text-danger">*</span></label>
                        <select name="papel" class="form-select @error('papel') is-invalid @enderror" required>
                            <option value="">Selecione...</option>
                            @foreach($papeis as $papel)
                                <option value="{{ $papel }}" {{ old('papel') == $papel ? 'selected' : '' }}>{{ $papel }}</option>
                            @endforeach
                        </select>
                        @error('papel') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="hidden" name="atende" value="0">
                            <input type="checkbox" name="atende" value="1" class="form-check-input" id="atende" {{ old('atende') ? 'checked' : '' }}>
                            <label class="form-check-label" for="atende">Este usuário realiza atendimentos</label>
                        </div>
                    </div>
                </div>

                {{-- Empresas com acesso (pivot empresa_usuario). Admin acessa todas automaticamente; nao-admin precisa de >= 1. --}}
                <div class="row" id="bloco-empresas-acesso">
                    <div class="col-12">
                        <label class="form-label d-block">Empresas com acesso <span class="text-danger" data-papel-required>*</span></label>
                        <small class="text-muted d-block mb-2" data-papel-admin-msg style="display:none !important;">Admin acessa todas as empresas da rede automaticamente.</small>
                        <div class="row">
                            @foreach($empresas as $empresa)
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input type="checkbox" name="empresas[]" value="{{ $empresa->id }}" class="form-check-input" id="empresa-{{ $empresa->id }}" {{ in_array($empresa->id, old('empresas', [])) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="empresa-{{ $empresa->id }}">{{ $empresa->nome }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('empresas') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        @error('empresas.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <x-form-botoes :voltar="route('usuarios.index')" />

        <script>
            // Esconde/mostra bloco de empresas conforme papel = Admin (Admin nao precisa de pivot).
            (function () {
                const selectPapel = document.querySelector('select[name="papel"]');
                const bloco = document.getElementById('bloco-empresas-acesso');
                if (!selectPapel || !bloco) return;
                const checkboxes = bloco.querySelectorAll('input[name="empresas[]"]');
                const requiredMark = bloco.querySelector('[data-papel-required]');
                const adminMsg = bloco.querySelector('[data-papel-admin-msg]');
                const grid = bloco.querySelector('.row');
                const aplicar = () => {
                    const ehAdmin = selectPapel.value === 'Admin';
                    if (ehAdmin) {
                        checkboxes.forEach(cb => { cb.checked = false; cb.disabled = true; });
                        if (requiredMark) requiredMark.style.display = 'none';
                        if (adminMsg) adminMsg.style.display = 'block';
                        if (grid) grid.style.display = 'none';
                    } else {
                        checkboxes.forEach(cb => { cb.disabled = false; });
                        if (requiredMark) requiredMark.style.display = '';
                        if (adminMsg) adminMsg.style.display = 'none';
                        if (grid) grid.style.display = '';
                    }
                };
                selectPapel.addEventListener('change', aplicar);
                aplicar();
            })();
        </script>
    </form>
@endsection
