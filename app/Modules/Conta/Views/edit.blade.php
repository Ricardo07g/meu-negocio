@extends('layouts.app')

@section('titulo', 'Editar Conta - Meu Negócio')
@section('titulo-pagina', 'Editar Conta')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('contas.index') }}">Contas</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <form action="{{ route('contas.update', $conta) }}" method="POST">
        @csrf @method('PUT')

        @include('conta::_form', ['entidade' => $conta])

        <x-form-botoes :voltar="route('contas.index')" />
    </form>
@endsection
