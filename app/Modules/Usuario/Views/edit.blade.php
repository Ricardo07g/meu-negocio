@extends('layouts.app')

@section('titulo', 'Editar Usuário - Meu Negócio')
@section('titulo-pagina', 'Editar Usuário')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('usuarios.index') }}">Usuários</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <form action="{{ route('usuarios.update', $usuario) }}" method="POST">
        @csrf @method('PUT')

        @include('usuario::_form', ['entidade' => $usuario])

        <x-form-botoes :voltar="route('usuarios.index')" />
    </form>
@endsection
