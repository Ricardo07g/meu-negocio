@extends('layouts.app')

@section('titulo', 'Editar Categoria de Despesa - Meu Negócio')
@section('titulo-pagina', 'Editar Categoria')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('despesas.index') }}">Contas a Pagar</a></li>
    <li class="breadcrumb-item"><a href="{{ route('categorias-despesa.index') }}">Categorias</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <form action="{{ route('categorias-despesa.update', $categoria) }}" method="POST">
        @csrf @method('PUT')

        @include('despesa::categorias._form', ['entidade' => $categoria])

        <x-form-botoes :voltar="route('categorias-despesa.index')" />
    </form>
@endsection
