@extends('layouts.app')

@section('titulo', 'Editar Categoria - Meu Negócio')
@section('titulo-pagina', 'Editar Categoria')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('produtos.index') }}">Produtos</a></li>
    <li class="breadcrumb-item"><a href="{{ route('categorias-produto.index') }}">Categorias</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <form action="{{ route('categorias-produto.update', $categoria) }}" method="POST">
        @csrf @method('PUT')

        @include('produto::categorias._form', ['entidade' => $categoria])

        <x-form-botoes :voltar="route('categorias-produto.index')" />
    </form>
@endsection
