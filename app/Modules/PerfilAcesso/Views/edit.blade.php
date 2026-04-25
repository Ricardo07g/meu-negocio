@extends('layouts.app')

@section('titulo', 'Editar Perfil de Acesso - Meu Negócio')
@section('titulo-pagina', 'Editar Perfil de Acesso')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('perfis-acesso.index') }}">Perfis de Acesso</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <form action="{{ route('perfis-acesso.update', $perfilAcesso) }}" method="POST">
        @csrf @method('PUT')
        @include('perfilacesso::_form')
    </form>
@endsection
