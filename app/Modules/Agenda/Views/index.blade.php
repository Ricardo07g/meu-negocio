@extends('layouts.app')

@section('titulo', 'Agenda - Meu Negócio')
@section('titulo-pagina', 'Agenda')
@section('breadcrumb')
    <li class="breadcrumb-item active">Agendamentos</li>
@endsection

@push('css')
<style>
    .fc { font-size: 13px; }
    .fc .fc-toolbar-title { font-size: 1.2em; }
    .fc .fc-event { cursor: pointer; border: none; padding: 2px 4px; }
    .fc .fc-timegrid-slot { height: 2.5em; }
    .fc .fc-col-header-cell { background: #f8f9fa; }
    .fc .fc-button-primary { background-color: var(--cor-destaque); border-color: var(--cor-destaque); }
    .fc .fc-button-primary:hover { background-color: var(--cor-destaque); opacity: 0.9; }
    .fc .fc-button-primary:not(:disabled).fc-button-active,
    .fc .fc-button-primary:not(:disabled):active { background-color: var(--cor-destaque); border-color: var(--cor-destaque); }
    .filtro-card .form-check { padding: 6px 12px 6px 36px; border-radius: 4px; transition: background .15s; }
    .filtro-card .form-check:hover { background: #f0f0f0; }
    .cor-profissional { display: inline-block; width: 12px; height: 12px; border-radius: 3px; margin-right: 6px; }
</style>
@endpush

@section('content')
<div class="row">
    {{-- Sidebar --}}
    <div class="col-xl-3 col-lg-4">
        {{-- Botão novo agendamento --}}
        @can('agendamento.criar')
        <a href="{{ route('vendas.create') }}" class="btn btn-primary w-100 mb-3">
            <i class="feather-plus me-2"></i>Novo Agendamento
        </a>
        @endcan

        {{-- Filtros profissionais --}}
        @if($profissionais->isNotEmpty())
        <div class="card stretch stretch-full filtro-card">
            <div class="card-header py-2">
                <h6 class="card-title mb-0 fs-13">Profissionais</h6>
            </div>
            <div class="card-body p-0">
                <div class="form-check border-bottom px-3 py-2" style="padding-left: 36px;">
                    <input type="checkbox" class="form-check-input" id="filtro-todos" checked>
                    <label class="form-check-label fw-semibold fs-13" for="filtro-todos">Todos</label>
                </div>
                @foreach($profissionais as $i => $prof)
                <div class="form-check">
                    <input type="checkbox" class="form-check-input filtro-profissional"
                           value="{{ $prof->id }}" id="filtro-prof-{{ $prof->id }}" checked>
                    <label class="form-check-label d-flex align-items-center fs-13" for="filtro-prof-{{ $prof->id }}">
                        <span class="cor-profissional" style="background: {{ $cores[$i % count($cores)] }};"></span>
                        {{ $prof->usuario->nome }}
                    </label>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Calendário --}}
    <div class="col-xl-9 col-lg-8">
        <div class="card stretch stretch-full">
            <div class="card-body">
                <div id="calendar" data-events-url="{{ route('agenda.json') }}"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
@vite('resources/js/calendar.js')
@endpush
