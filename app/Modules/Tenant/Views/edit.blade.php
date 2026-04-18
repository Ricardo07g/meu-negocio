@extends('layouts.app')

@section('titulo', 'Editar Empresa - Meu Negócio')
@section('titulo-pagina', 'Editar Empresa')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('empresas.index') }}">Empresas</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <form action="{{ route('empresas.update', $empresa) }}" method="POST">
        @csrf @method('PUT')

        @include('tenant::_form', ['entidade' => $empresa])

        <x-form-botoes :voltar="route('empresas.index')" />
    </form>
@endsection
