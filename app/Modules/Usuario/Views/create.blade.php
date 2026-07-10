@extends('layouts.app')

@section('titulo', 'Novo Usuário - Meu Negócio')
@section('titulo-pagina', 'Novo Usuário')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('usuarios.index') }}">Usuários</a></li>
    <li class="breadcrumb-item active">Novo</li>
@endsection

@section('content')
    <form action="{{ route('usuarios.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        @include('usuario::_form')

        <x-form-botoes :voltar="route('usuarios.index')" />
    </form>
@endsection
