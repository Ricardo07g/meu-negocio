@extends('layouts.app')

@section('titulo', 'Editar Despesa - Meu Negócio')
@section('titulo-pagina', 'Editar Despesa')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('despesas.index') }}">Contas a Pagar</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <form action="{{ route('despesas.update', $despesa) }}" method="POST">
        @csrf @method('PUT')

        @include('despesa::_form', ['entidade' => $despesa])

        <x-form-botoes :voltar="route('despesas.index')" />
    </form>
@endsection
