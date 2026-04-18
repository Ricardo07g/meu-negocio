@extends('layouts.app')

@section('titulo', 'Novo Produto - Meu Negócio')
@section('titulo-pagina', 'Novo Produto')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('produtos.index') }}">Produtos</a></li>
    <li class="breadcrumb-item active">Novo</li>
@endsection

@section('content')
    <form action="{{ route('produtos.store') }}" method="POST">
        @csrf

        @include('produto::_form', ['entidade' => null, 'categorias' => $categorias])

        <x-form-botoes :voltar="route('produtos.index')" />
    </form>
@endsection
