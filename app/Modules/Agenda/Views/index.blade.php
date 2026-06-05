@extends('layouts.app')

@section('titulo', 'Agenda - Meu Negócio')
@section('titulo-pagina', 'Agenda')
@section('breadcrumb')
    <li class="breadcrumb-item active">Agendamentos</li>
@endsection

@push('css')
<style>
    .agenda-toolbar { gap: .5rem; }
    .agenda-toolbar .btn-icon { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; }
    #calendar { height: 720px; }
    .agenda-sidebar .form-check-input { cursor: pointer; }
    .agenda-sidebar .form-check-label { cursor: pointer; }

    /* Modal de detalhes do agendamento — SweetAlert2 customizado */
    .swal-agenda .swal2-title { padding-top: 1.25rem !important; padding-bottom: .25rem !important; font-size: 1.05rem !important; font-weight: 600; }
    .swal-agenda .swal2-html-container { padding: 0 1.5rem 1.5rem !important; margin: .75rem 0 0 !important; text-align: left; }

    .swal-agenda .swal-status { text-align: center; }
    .swal-agenda .swal-status-pill { display: inline-flex; align-items: center; gap: .4rem; padding: .35rem .85rem; border-radius: 1rem; font-size: .8rem; font-weight: 600; }
    .swal-agenda .swal-status-pill .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

    .swal-agenda .swal-info { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: .5rem; padding: .35rem 1.15rem; margin-bottom: 1rem; }
    .swal-agenda .swal-info-row { display: flex; justify-content: space-between; align-items: center; padding: .55rem 0; gap: 1rem; font-size: .9rem; border-bottom: 1px dashed #e9ecef; }
    .swal-agenda .swal-info-row:last-child { border-bottom: none; }
    .swal-agenda .swal-info-row .label { color: #6c757d; font-weight: 500; min-width: 90px; text-transform: capitalize; }
    .swal-agenda .swal-info-row .value { color: #212529; font-weight: 600; text-align: right; flex: 1; word-break: break-word; }

    .swal-agenda .swal-obs { background: #fff8e1; border-left: 3px solid #ffc107; padding: .65rem .95rem; border-radius: .25rem; margin-bottom: 1rem; font-size: .85rem; color: #5c4a00; }
    .swal-agenda .swal-obs strong { color: #856404; margin-right: .35rem; }

    .swal-agenda .swal-actions { display: flex; flex-wrap: wrap; gap: .55rem; margin-top: .25rem; }
    .swal-agenda .swal-actions .btn { flex: 1 1 calc(50% - .3rem); min-width: 130px; padding: .65rem .9rem; font-size: .9rem; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; }
    .swal-agenda .swal-actions .btn.swal-btn-full { flex: 1 1 100%; }

    /* Modal de reagendamento — campos datetime-local */
    .swal-reagendar .swal2-html-container { padding: 0 1.5rem !important; margin: 1rem 0 0 !important; text-align: left; }
    .swal-reagendar .swal-hint { font-size: .82rem; color: #6c757d; }
    .swal-reagendar .swal-field { margin-bottom: 1rem; }
    .swal-reagendar .swal-field label { display: block; font-size: .8rem; font-weight: 600; color: #495057; margin-bottom: .35rem; letter-spacing: .02em; }
    .swal-reagendar .swal-field input.form-control { width: 100%; padding: .55rem .75rem; font-size: .95rem; border: 1px solid #dee2e6; border-radius: .375rem; box-sizing: border-box; transition: border-color .15s, box-shadow .15s; }
    .swal-reagendar .swal-field input.form-control:focus { border-color: #3454d1; box-shadow: 0 0 0 0.2rem rgba(52,84,209,.15); outline: 0; }
</style>
@endpush

@section('content')
@include('partials.filtro-empresa-listagem')
<div class="row g-3">
    {{-- Sidebar (card único) --}}
    <div class="col-xl-3 col-lg-4 agenda-sidebar">
        <div class="card stretch stretch-full">
            <div class="card-body">
                <button type="button" class="btn btn-primary w-100 mb-3" data-bs-toggle="modal" data-bs-target="#modalNovoAgendamento">
                    <i class="feather-plus me-2"></i>Novo Agendamento
                </button>

                <div class="fs-12 text-muted fw-semibold text-uppercase mb-2">Atendentes</div>
                <div class="form-check py-1 border-bottom mb-2 pb-2">
                    <input class="form-check-input" type="checkbox" id="filtro-todos" checked>
                    <label class="form-check-label fw-semibold fs-13" for="filtro-todos">Todos</label>
                </div>
                <div id="filtros-atendentes" class="mb-3">
                    <div class="text-muted fs-12 py-2">Carregando…</div>
                </div>

                <hr class="border-dashed my-3">

                <div class="fs-12 text-muted fw-semibold text-uppercase mb-2">Status</div>
                @foreach([
                    'agendado' => ['Agendado', 'info'],
                    'confirmado' => ['Confirmado', 'primary'],
                    'finalizado' => ['Finalizado', 'success'],
                    'cancelado' => ['Cancelado', 'danger'],
                ] as $valor => $info)
                    <div class="form-check d-flex align-items-center gap-2 py-1">
                        <input class="form-check-input filtro-status" type="checkbox" id="fs-{{ $valor }}" value="{{ $valor }}" checked>
                        <label class="form-check-label d-flex align-items-center flex-grow-1 fs-13" for="fs-{{ $valor }}">
                            <span class="badge bg-soft-{{ $info[1] }} text-{{ $info[1] }} me-2">●</span>
                            {{ $info[0] }}
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Calendário --}}
    <div class="col-xl-9 col-lg-8">
        <div class="card stretch stretch-full">
            <div class="card-header d-flex flex-wrap align-items-center agenda-toolbar py-2">
                <div class="btn-group me-2">
                    <button type="button" id="cal-prev" class="btn btn-light btn-icon" aria-label="Anterior"><i class="feather-chevron-left"></i></button>
                    <button type="button" id="cal-today" class="btn btn-light">Hoje</button>
                    <button type="button" id="cal-next" class="btn btn-light btn-icon" aria-label="Próximo"><i class="feather-chevron-right"></i></button>
                </div>
                <div id="cal-range" class="fw-semibold fs-14 me-auto"></div>
                <div class="btn-group">
                    <button type="button" class="btn btn-light" data-view="day"><i class="feather-list me-1"></i>Dia</button>
                    <button type="button" class="btn btn-light active" data-view="week"><i class="feather-sliders me-1"></i>Semana</button>
                    <button type="button" class="btn btn-light" data-view="month"><i class="feather-grid me-1"></i>Mês</button>
                </div>
            </div>
            <div class="card-body">
                <div id="calendar"
                     data-events-url="{{ route('agenda.json') }}"
                     data-criar-url="{{ route('agenda.criar-rapido') }}"
                     data-reagendar-template="{{ url('agenda/__ID__/reagendar') }}">
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Novo Agendamento --}}
<div class="modal fade" id="modalNovoAgendamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formNovoAgendamento">
                <div class="modal-header">
                    <h5 class="modal-title">Novo Agendamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Cliente</label>
                        <input type="text" class="form-control" id="agenda-cliente-input" placeholder="Digite para buscar cliente..." autocomplete="off" required>
                        <input type="hidden" name="cliente_id" id="agenda-cliente-id">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Serviço</label>
                        <input type="text" class="form-control" id="agenda-servico-input" placeholder="Digite para buscar serviço..." autocomplete="off" required>
                        <input type="hidden" name="servico_id" id="agenda-servico-id">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Atendente</label>
                        <select name="atendente_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            @foreach($atendentes as $atendente)
                                <option value="{{ $atendente->id }}">{{ $atendente->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Início</label>
                        <input type="datetime-local" name="inicio" class="form-control" required>
                        <small class="text-muted">Fim será calculado pela duração do serviço.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Agendar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof initAjaxSearch === 'function') {
        initAjaxSearch({
            inputId: 'agenda-cliente-input',
            hiddenId: 'agenda-cliente-id',
            url: '{{ route('clientes.buscar') }}',
            renderItem: function (c) {
                return '<div class="fw-semibold">' + (c.nome || '') + '</div>' +
                    (c.telefone ? '<div class="fs-12 text-muted">' + c.telefone + '</div>' : '');
            },
            displayText: function (c) { return c.nome; },
        });
        initAjaxSearch({
            inputId: 'agenda-servico-input',
            hiddenId: 'agenda-servico-id',
            url: '{{ route('servicos.buscar') }}',
            renderItem: function (s) {
                return '<div class="fw-semibold">' + (s.nome || '') + '</div>' +
                    (s.duracao ? '<div class="fs-12 text-muted">' + s.duracao + ' min</div>' : '');
            },
            displayText: function (s) { return s.nome; },
        });
    }
});
</script>
@vite('resources/js/calendar.js')
@endpush
