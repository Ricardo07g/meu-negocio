@extends('layouts.app')

@section('titulo', 'Editar Produto - Meu Negócio')
@section('titulo-pagina', 'Editar Produto')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('produtos.index') }}">Produtos</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <form action="{{ route('produtos.update', $produto) }}" method="POST">
        @csrf @method('PUT')

        @include('produto::_form', ['entidade' => $produto, 'categorias' => $categorias])

        <x-form-botoes :voltar="route('produtos.index')" />
    </form>
@endsection
