@extends('layouts.auth')

@section('titulo', 'Redefinir Senha - Meu Negócio')

@section('content')
    <h2 class="fs-20 fw-bolder mb-4">Redefinir senha</h2>
    <h4 class="fs-13 fw-bold mb-2">Defina sua nova senha</h4>
    <p class="fs-12 fw-medium text-muted">Escolha uma nova senha com no mínimo 8 caracteres. O link expira em 60 minutos.</p>

    <form action="{{ route('senha.redefinir') }}" method="POST" class="w-100 mt-4 pt-2">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-4">
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                   placeholder="Email" value="{{ old('email', $email) }}" required readonly>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-4">
            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                   placeholder="Nova senha (mín. 8 caracteres)" required autofocus>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-4">
            <input type="password" name="password_confirmation" class="form-control"
                   placeholder="Confirmar nova senha" required>
        </div>
        <div class="mt-5">
            <button type="submit" class="btn btn-lg btn-primary w-100">Redefinir senha</button>
        </div>
    </form>
    <div class="mt-4 text-center">
        <p class="fs-12 text-muted">Lembrou a senha? <a href="{{ route('login') }}">Voltar para login</a></p>
    </div>
@endsection
