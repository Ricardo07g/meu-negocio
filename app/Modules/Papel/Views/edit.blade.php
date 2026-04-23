@extends('layouts.app')

@section('titulo', 'Editar Papel - Meu Negócio')
@section('titulo-pagina', 'Editar Papel')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('papeis.index') }}">Papéis</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <form action="{{ route('papeis.update', $papel) }}" method="POST">
        @csrf @method('PUT')
        @include('papel::_form')
    </form>
@endsection
