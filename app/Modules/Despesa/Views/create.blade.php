@extends('layouts.app')

@section('titulo', 'Nova Despesa - Meu Negócio')
@section('titulo-pagina', 'Nova Despesa')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('despesas.index') }}">Contas a Pagar</a></li>
    <li class="breadcrumb-item active">Nova</li>
@endsection

@section('content')
    <form action="{{ route('despesas.store') }}" method="POST">
        @csrf

        @include('despesa::_form')

        <x-form-botoes :voltar="route('despesas.index')" />
    </form>
@endsection
