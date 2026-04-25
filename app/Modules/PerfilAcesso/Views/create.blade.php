@extends('layouts.app')

@section('titulo', 'Novo Perfil de Acesso - Meu Negócio')
@section('titulo-pagina', 'Novo Perfil de Acesso')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('perfis-acesso.index') }}">Perfis de Acesso</a></li>
    <li class="breadcrumb-item active">Novo</li>
@endsection

@section('content')
    <form action="{{ route('perfis-acesso.store') }}" method="POST">
        @csrf
        @include('perfilacesso::_form')
    </form>
@endsection
