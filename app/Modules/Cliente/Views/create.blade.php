@extends('layouts.app')

@section('titulo', 'Novo Cliente - Meu Negocio')
@section('titulo-pagina', 'Novo Cliente')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('clientes.index') }}">Clientes</a></li>
    <li class="breadcrumb-item active">Novo</li>
@endsection

@section('content')
    <form action="{{ route('clientes.store') }}" method="POST">
        @csrf

        @include('cliente::_form')

        <x-form-botoes :voltar="route('clientes.index')" />
    </form>
@endsection
