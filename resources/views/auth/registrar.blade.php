@extends('layouts.auth')

@section('titulo', 'Registrar - Meu Negócio')

@section('content')
    <h2 class="fs-20 fw-bolder mb-4">Criar Conta</h2>
    <h4 class="fs-13 fw-bold mb-2">Comece grátis</h4>
    <p class="fs-12 fw-medium text-muted">Crie sua conta no <strong>Meu Negócio</strong> e comece a gerenciar seus agendamentos, clientes e finanças.</p>

    <form action="{{ route('registrar') }}" method="POST" class="w-100 mt-4 pt-2">
        @csrf
        <div class="mb-4">
            <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror"
                   placeholder="Seu nome" value="{{ old('nome') }}" required>
            @error('nome')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-4">
            <input type="text" name="empresa" class="form-control @error('empresa') is-invalid @enderror"
                   placeholder="Nome da empresa" value="{{ old('empresa') }}" required>
            @error('empresa')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-4">
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                   placeholder="Email" value="{{ old('email') }}" required>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-4">
            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                   placeholder="Senha (mín. 8 caracteres)" required>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-4">
            <input type="password" name="password_confirmation" class="form-control"
                   placeholder="Confirmar senha" required>
        </div>
        <div class="mt-5">
            <button type="submit" class="btn btn-lg btn-primary w-100">Criar Conta</button>
        </div>
    </form>
    <div class="mt-4 text-center">
        <p class="fs-12 text-muted">Já tem conta? <a href="{{ route('login') }}">Fazer login</a></p>
    </div>
@endsection
