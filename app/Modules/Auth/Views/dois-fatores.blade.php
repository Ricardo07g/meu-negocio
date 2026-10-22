@extends('layouts.auth')

@section('titulo', 'Verificação em duas etapas - Meu Negócio')

@section('content')
    <h2 class="fs-20 fw-bolder mb-4">Verificação em duas etapas</h2>
    <h4 class="fs-13 fw-bold mb-2">Confirme que é você</h4>
    <p class="fs-12 fw-medium text-muted">Abra o aplicativo autenticador no seu celular e digite o código de 6 dígitos do <strong>Meu Negócio</strong>.</p>

    <form action="{{ route('login.dois-fatores') }}" method="POST" class="w-100 mt-4 pt-2">
        @csrf
        <div class="mb-4">
            <input type="text" name="codigo" class="form-control text-center fs-18 @error('codigo') is-invalid @enderror"
                   placeholder="000000" inputmode="numeric" autocomplete="one-time-code" maxlength="7" autofocus>
            @error('codigo')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn btn-lg btn-primary w-100">Verificar</button>
    </form>

    {{-- <details> nativo: abre sem JS e ja vem aberto quando o erro e do codigo de recuperacao. --}}
    <details class="mt-4" @if($errors->has('codigo_recuperacao')) open @endif>
        <summary class="fs-12 text-muted">Perdeu o acesso ao aplicativo? Use um código de recuperação</summary>
        <form action="{{ route('login.dois-fatores') }}" method="POST" class="w-100 mt-3">
            @csrf
            <div class="mb-3">
                <input type="text" name="codigo_recuperacao" class="form-control text-center @error('codigo_recuperacao') is-invalid @enderror"
                       placeholder="XXXXX-XXXXX" autocomplete="off" maxlength="30">
                @error('codigo_recuperacao')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-light w-100">Entrar com código de recuperação</button>
        </form>
    </details>

    <div class="mt-4 text-center">
        <p class="fs-12 text-muted"><a href="{{ route('login') }}">Voltar para o login</a></p>
    </div>
@endsection
