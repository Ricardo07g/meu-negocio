@extends('layouts.app')

@section('titulo', 'Nova Categoria - Meu Negócio')
@section('titulo-pagina', 'Nova Categoria')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('produtos.index') }}">Produtos</a></li>
    <li class="breadcrumb-item"><a href="{{ route('categorias-produto.index') }}">Categorias</a></li>
    <li class="breadcrumb-item active">Nova</li>
@endsection

@section('content')
    <form action="{{ route('categorias-produto.store') }}" method="POST">
        @csrf

        @include('produto::categorias._form')

        <x-form-botoes :voltar="route('categorias-produto.index')" />
    </form>
@endsection
