@extends('layouts.app')

@section('titulo', 'Agenda - Meu Negócio')
@section('titulo-pagina', 'Agenda')
@section('breadcrumb')
    <li class="breadcrumb-item active">Agenda</li>
@endsection

@section('content')
    {{-- Date navigation --}}
    <div class="row mb-4 align-items-center text-center">
        <div class="col-4 text-start">
            <a href="{{ route('agenda.index', ['data' => $data->copy()->subDay()->format('Y-m-d')]) }}" class="btn btn-primary w-100">
                <i class="feather-chevron-left me-2"></i>Anterior
            </a>
        </div>
        <div class="col-4">
            <h5 class="mb-0">{{ $data->format('d/m/Y') }}</h5>
            <small class="text-muted text-capitalize">{{ $data->locale('pt_BR')->isoFormat('dddd') }}</small>
        </div>
        <div class="col-4 text-end">
            <a href="{{ route('agenda.index', ['data' => $data->copy()->addDay()->format('Y-m-d')]) }}" class="btn btn-primary w-100">
                Próximo<i class="feather-chevron-right ms-2"></i>
            </a>
        </div>
    </div>
    @if(!$data->isToday())
    <div class="text-center mb-4">
        <a href="{{ route('agenda.index') }}" class="text-muted fs-12">Ir para hoje</a>
    </div>
    @endif

    {{-- Card with table --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Horário</th>
                            <th>Cliente</th>
                            <th>Profissional</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($agendamentos as $agendamento)
                        <tr>
                            <td>{{ $agendamento->inicio->format('H:i') }} - {{ $agendamento->fim->format('H:i') }}</td>
                            <td>{{ $agendamento->cliente->nome ?? '-' }}</td>
                            <td>{{ $agendamento->profissional->usuario->nome ?? '-' }}</td>
                            <td>
                                @switch($agendamento->status->value)
                                    @case('agendado') <span class="badge bg-info">Agendado</span> @break
                                    @case('confirmado') <span class="badge bg-primary">Confirmado</span> @break
                                    @case('finalizado') <span class="badge bg-success">Finalizado</span> @break
                                    @case('cancelado') <span class="badge bg-danger">Cancelado</span> @break
                                    @default <span class="badge bg-secondary">{{ ucfirst($agendamento->status->value) }}</span>
                                @endswitch
                            </td>
                            <td>
                                <div class="hstack gap-2 justify-content-end">
                                    <div class="dropdown">
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item btn-ver-agendamento" href="javascript:void(0)" data-url="{{ route('agenda.show', $agendamento) }}">
                                                    <i class="feather-eye me-3"></i>
                                                    <span>Ver</span>
                                                </a>
                                            </li>
                                            @if(!in_array($agendamento->status->value, ['cancelado', 'finalizado']))
                                            @can('agendamento.editar')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('agenda.edit', $agendamento) }}">
                                                    <i class="feather-edit-3 me-3"></i>
                                                    <span>Editar</span>
                                                </a>
                                            </li>
                                            @endcan
                                            @endif
                                            <li class="dropdown-divider"></li>
                                            @if($agendamento->status->value === 'agendado')
                                            <li>
                                                <form action="{{ route('agenda.confirmar', $agendamento) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="dropdown-item text-success">
                                                        <i class="feather-check me-3"></i>
                                                        <span>Confirmar</span>
                                                    </button>
                                                </form>
                                            </li>
                                            @endif
                                            @if(in_array($agendamento->status->value, ['agendado', 'confirmado']))
                                            <li>
                                                <form action="{{ route('agenda.finalizar', $agendamento) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="dropdown-item text-success">
                                                        <i class="feather-check-circle me-3"></i>
                                                        <span>Finalizar</span>
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <form action="{{ route('agenda.cancelar', $agendamento) }}" method="POST" data-confirm="Cancelar este agendamento?">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="feather-x-circle me-3"></i>
                                                        <span>Cancelar</span>
                                                    </button>
                                                </form>
                                            </li>
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhum agendamento para esta data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal de visualização --}}
    <div class="modal fade" id="modalVerAgendamento" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Agendamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-3">
                            <span class="text-muted"><i class="feather-user me-2"></i>Cliente</span>
                            <span id="modalCliente" class="fw-medium"></span>
                        </li>
                        <li class="hstack justify-content-between mb-3">
                            <span class="text-muted"><i class="feather-briefcase me-2"></i>Serviço</span>
                            <span id="modalServico" class="fw-medium"></span>
                        </li>
                        <li class="hstack justify-content-between mb-3">
                            <span class="text-muted"><i class="feather-users me-2"></i>Profissional</span>
                            <span id="modalProfissional" class="fw-medium"></span>
                        </li>
                        <li class="hstack justify-content-between mb-3">
                            <span class="text-muted"><i class="feather-calendar me-2"></i>Data</span>
                            <span id="modalData" class="fw-medium"></span>
                        </li>
                        <li class="hstack justify-content-between mb-3">
                            <span class="text-muted"><i class="feather-clock me-2"></i>Horário</span>
                            <span id="modalHorario" class="fw-medium"></span>
                        </li>
                        <li class="hstack justify-content-between mb-3">
                            <span class="text-muted"><i class="feather-info me-2"></i>Status</span>
                            <span id="modalStatus"></span>
                        </li>
                        <li class="hstack justify-content-between" id="modalObsRow" style="display:none;">
                            <span class="text-muted"><i class="feather-file-text me-2"></i>Obs.</span>
                            <span id="modalObs" class="fw-medium"></span>
                        </li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <a href="#" id="modalBtnEditar" class="btn btn-primary"><i class="feather-edit me-1"></i> Editar</a>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = new bootstrap.Modal(document.getElementById('modalVerAgendamento'));
    var statusBadges = {
        agendado: '<span class="badge bg-info">Agendado</span>',
        confirmado: '<span class="badge bg-primary">Confirmado</span>',
        finalizado: '<span class="badge bg-success">Finalizado</span>',
        cancelado: '<span class="badge bg-danger">Cancelado</span>'
    };

    document.querySelectorAll('.btn-ver-agendamento').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var url = this.dataset.url;
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    document.getElementById('modalCliente').textContent = data.cliente;
                    document.getElementById('modalServico').textContent = data.servico;
                    document.getElementById('modalProfissional').textContent = data.profissional;
                    document.getElementById('modalData').textContent = data.data;
                    document.getElementById('modalHorario').textContent = data.horario;
                    document.getElementById('modalStatus').innerHTML = statusBadges[data.status] || data.status;
                    document.getElementById('modalBtnEditar').href = data.edit_url;

                    var obsRow = document.getElementById('modalObsRow');
                    if (data.observacoes && data.observacoes !== '-') {
                        document.getElementById('modalObs').textContent = data.observacoes;
                        obsRow.style.display = 'flex';
                    } else {
                        obsRow.style.display = 'none';
                    }

                    if (['cancelado', 'finalizado'].includes(data.status)) {
                        document.getElementById('modalBtnEditar').style.display = 'none';
                    } else {
                        document.getElementById('modalBtnEditar').style.display = '';
                    }

                    modal.show();
                });
        });
    });
});
</script>
@endpush
