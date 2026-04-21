@extends('layouts.app')

@section('titulo', 'Nova Categoria de Despesa - Meu Negócio')
@section('titulo-pagina', 'Nova Categoria')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('despesas.index') }}">Contas a Pagar</a></li>
    <li class="breadcrumb-item"><a href="{{ route('categorias-despesa.index') }}">Categorias</a></li>
    <li class="breadcrumb-item active">Nova</li>
@endsection

@section('content')
    <form action="{{ route('categorias-despesa.store') }}" method="POST">
        @csrf

        @include('despesa::categorias._form')

        <x-form-botoes :voltar="route('categorias-despesa.index')" />
    </form>
@endsection
