@extends('layouts.app')

@section('titulo', 'Nova Empresa - Meu Negócio')
@section('titulo-pagina', 'Nova Empresa')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('empresas.index') }}">Empresas</a></li>
    <li class="breadcrumb-item active">Nova</li>
@endsection

@section('content')
    <form action="{{ route('empresas.store') }}" method="POST">
        @csrf

        @include('tenant::_form')

        <x-form-botoes :voltar="route('empresas.index')" />
    </form>
@endsection
