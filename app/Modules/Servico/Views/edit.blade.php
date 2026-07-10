@extends('layouts.app')

@section('titulo', 'Editar Serviço - Meu Negócio')
@section('titulo-pagina', 'Editar Serviço')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('servicos.index') }}">Serviços</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <form action="{{ route('servicos.update', $servico) }}" method="POST" enctype="multipart/form-data">
        @csrf @method('PUT')

        @include('servico::_form', ['entidade' => $servico])

        <x-form-botoes :voltar="route('servicos.index')" />
    </form>
@endsection
