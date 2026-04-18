@extends('layouts.app')

@section('titulo', 'Editar Cliente - Meu Negocio')
@section('titulo-pagina', 'Editar Cliente')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('clientes.index') }}">Clientes</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <form action="{{ route('clientes.update', $cliente) }}" method="POST">
        @csrf @method('PUT')

        @include('cliente::_form', ['entidade' => $cliente])

        <x-form-botoes :voltar="route('clientes.index')" />
    </form>
@endsection
