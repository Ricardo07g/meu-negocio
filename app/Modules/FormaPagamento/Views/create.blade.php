@extends('layouts.app')

@section('titulo', 'Nova Forma de Pagamento - Meu Negócio')
@section('titulo-pagina', 'Nova Forma de Pagamento')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('formas-pagamento.index') }}">Formas de Pagamento</a></li>
    <li class="breadcrumb-item active">Nova</li>
@endsection

@section('content')
    <form action="{{ route('formas-pagamento.store') }}" method="POST">
        @csrf

        @include('formapagamento::_form')

        <x-form-botoes :voltar="route('formas-pagamento.index')" />
    </form>
@endsection
