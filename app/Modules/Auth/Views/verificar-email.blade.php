@extends('layouts.auth')

@section('titulo', 'Confirme seu e-mail - Meu Negócio')

@section('content')
    <h2 class="fs-20 fw-bolder mb-4">Confirme seu e-mail</h2>
    <h4 class="fs-13 fw-bold mb-2">Falta só um passo</h4>
    <p class="fs-12 fw-medium text-muted">
        Enviamos um link de confirmação para <strong>{{ $email }}</strong>.
        Abra sua caixa de entrada e clique no botão do e-mail para liberar o acesso.
    </p>
    <p class="fs-12 text-muted">Não chegou? Confira a pasta de spam ou peça um novo link.</p>

    {{-- O flash e do layout (layouts/auth). --}}

    <form action="{{ route('verificacao.reenviar') }}" method="POST" class="w-100 mt-4 pt-2">
        @csrf
        <button type="submit" class="btn btn-lg btn-primary w-100">Reenviar link de confirmação</button>
    </form>
    <form action="{{ route('logout') }}" method="POST" class="mt-4 text-center">
        @csrf
        <button type="submit" class="btn btn-link fs-12 text-muted">Sair e entrar com outra conta</button>
    </form>
@endsection
