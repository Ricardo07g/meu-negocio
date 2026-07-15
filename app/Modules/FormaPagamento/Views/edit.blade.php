@extends('layouts.app')

@section('titulo', 'Editar Forma de Pagamento - Meu Negócio')
@section('titulo-pagina', 'Editar Forma de Pagamento')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('formas-pagamento.index') }}">Formas de Pagamento</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <form action="{{ route('formas-pagamento.update', $forma) }}" method="POST">
        @csrf @method('PUT')

        @include('formapagamento::_form', ['entidade' => $forma])

        <x-form-botoes :voltar="route('formas-pagamento.index')" />
    </form>
@endsection
