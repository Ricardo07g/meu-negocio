@extends('layouts.app')

@section('titulo', 'Reagendar - Meu Negócio')
@section('titulo-pagina', 'Reagendar')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('agenda.index') }}">Agenda</a></li>
    <li class="breadcrumb-item active">Reagendar</li>
@endsection

@section('content')
    <form action="{{ route('agenda.update', $agendamento) }}" method="POST">
        @csrf @method('PUT')

        @include('agenda::_form', ['entidade' => $agendamento])

        <x-form-botoes :voltar="route('agenda.index')" />
    </form>
@endsection
