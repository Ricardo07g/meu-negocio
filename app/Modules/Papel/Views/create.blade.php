@extends('layouts.app')

@section('titulo', 'Novo Papel - Meu Negócio')
@section('titulo-pagina', 'Novo Papel')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('papeis.index') }}">Papéis</a></li>
    <li class="breadcrumb-item active">Novo</li>
@endsection

@section('content')
    <form action="{{ route('papeis.store') }}" method="POST">
        @csrf
        @include('papel::_form')
    </form>
@endsection
