@extends('layouts.app')

@section('titulo', 'Novo Serviço - Meu Negócio')
@section('titulo-pagina', 'Novo Serviço')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('servicos.index') }}">Serviços</a></li>
    <li class="breadcrumb-item active">Novo</li>
@endsection

@section('content')
    <form action="{{ route('servicos.store') }}" method="POST">
        @csrf

        @include('servico::_form')

        <x-form-botoes :voltar="route('servicos.index')" />
    </form>
@endsection
