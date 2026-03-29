@extends('layouts.auth')

@section('titulo', 'Login - Meu Negócio')

@section('content')
    <h2 class="fs-20 fw-bolder mb-4">Login</h2>
    <h4 class="fs-13 fw-bold mb-2">Acesse sua conta</h4>
    <p class="fs-12 fw-medium text-muted">Bem-vindo ao <strong>Meu Negócio</strong>. Entre com suas credenciais para acessar o sistema.</p>

    <form action="{{ route('login') }}" method="POST" class="w-100 mt-4 pt-2">
        @csrf
        <div class="mb-4">
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                   placeholder="Email" value="{{ old('email') }}" required autofocus>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                   placeholder="Senha" required>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mt-5">
            <button type="submit" class="btn btn-lg btn-primary w-100">Entrar</button>
        </div>
    </form>
    <div class="mt-4 text-center">
        <p class="fs-12 text-muted">Não tem conta? <a href="{{ route('registrar') }}">Criar conta grátis</a></p>
    </div>
@endsection
