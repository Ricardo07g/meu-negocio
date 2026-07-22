@extends('layouts.app')

@section('titulo', 'Nova Conta - Meu Negócio')
@section('titulo-pagina', 'Nova Conta')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('contas.index') }}">Contas</a></li>
    <li class="breadcrumb-item active">Nova</li>
@endsection

@section('content')
    <form action="{{ route('contas.store') }}" method="POST">
        @csrf

        @include('conta::_form')

        <x-form-botoes :voltar="route('contas.index')" />
    </form>
@endsection
