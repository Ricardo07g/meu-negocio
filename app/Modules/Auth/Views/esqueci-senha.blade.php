@extends('layouts.auth')

@section('titulo', 'Recuperar Senha - Meu Negócio')

@section('content')
    <h2 class="fs-20 fw-bolder mb-4">Esqueci minha senha</h2>
    <h4 class="fs-13 fw-bold mb-2">Vamos te ajudar a entrar de volta</h4>
    <p class="fs-12 fw-medium text-muted">Informe o email da sua conta no <strong>Meu Negócio</strong> e enviaremos um link para você definir uma nova senha.</p>

    @if(session('sucesso'))
        <div class="alert alert-success" role="alert">
            {{ session('sucesso') }}
        </div>
    @endif

    <form action="{{ route('senha.solicitar.enviar') }}" method="POST" class="w-100 mt-4 pt-2">
        @csrf
        <div class="mb-4">
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                   placeholder="Email" value="{{ old('email') }}" required autofocus>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mt-5">
            <button type="submit" class="btn btn-lg btn-primary w-100">Enviar link de recuperação</button>
        </div>
    </form>
    <div class="mt-4 text-center">
        <p class="fs-12 text-muted">Lembrou a senha? <a href="{{ route('login') }}">Voltar para login</a></p>
    </div>
@endsection
