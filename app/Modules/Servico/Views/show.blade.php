@extends('layouts.app')

@section('titulo', 'Serviço - Meu Negócio')
@section('titulo-pagina', $servico->nome)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('servicos.index') }}">Serviços</a></li>
    <li class="breadcrumb-item active">{{ $servico->nome }}</li>
@endsection

@section('content')
    <div class="row">
        {{-- Coluna esquerda: Dados do servico --}}
        <div class="col-xxl-4 col-xl-5">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="mb-4 text-center">
                        <div class="wd-80 ht-80 mx-auto mb-3">
                            <div class="avatar-text avatar-xl bg-primary text-white rounded-circle fs-24 fw-bold">
                                <i class="feather-clipboard"></i>
                            </div>
                        </div>
                        <h5 class="fw-bold mb-1">{{ $servico->nome }}</h5>
                        @if ($servico->isPacote())
                            <span class="badge bg-info">Pacote</span>
                        @else
                            <span class="badge bg-secondary">Avulso</span>
                        @endif
                    </div>

                    <ul class="list-unstyled mb-4">
                        {{-- Tipo --}}
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-tag"></i>Tipo
                            </span>
                            <span>{{ $servico->isPacote() ? 'Pacote' : 'Avulso' }}</span>
                        </li>
                        {{-- Duracao --}}
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-clock"></i>Duração
                            </span>
                            <span>{{ $servico->duracao }} min</span>
                        </li>
                        {{-- Valor --}}
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-dollar-sign"></i>Valor
                            </span>
                            <span class="fw-bold">R$ {{ number_format($servico->valor, 2, ',', '.') }}</span>
                        </li>
                        {{-- Sessoes (so para pacote) --}}
                        @if ($servico->isPacote())
                            <li class="hstack justify-content-between mb-4">
                                <span class="text-muted fw-medium hstack gap-3">
                                    <i class="feather-layers"></i>Sessões
                                </span>
                                <span class="fw-bold">{{ $servico->qtd_sessoes ?? '-' }}</span>
                            </li>
                            @if ($servico->qtd_sessoes && $servico->qtd_sessoes > 0)
                                <li class="hstack justify-content-between mb-4">
                                    <span class="text-muted fw-medium hstack gap-3">
                                        <i class="feather-trending-down"></i>Valor por sessão
                                    </span>
                                    <span>R$ {{ number_format($servico->valor / $servico->qtd_sessoes, 2, ',', '.') }}</span>
                                </li>
                            @endif
                        @endif
                        {{-- Total agendamentos --}}
                        <li class="hstack justify-content-between {{ $servico->descricao ? 'mb-4' : 'mb-0' }}">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-calendar"></i>Agendamentos
                            </span>
                            <span class="fw-bold">{{ $agendamentos->total() }}</span>
                        </li>
                        {{-- Descricao --}}
                        @if ($servico->descricao)
                            <li class="mb-0">
                                <span class="text-muted fw-medium hstack gap-3 mb-2">
                                    <i class="feather-file-text"></i>Descrição
                                </span>
                                <span class="fs-13 d-block">{{ $servico->descricao }}</span>
                            </li>
                        @endif
                    </ul>

                    <div class="d-flex gap-2 text-center pt-4">
                        <a href="{{ route('servicos.index') }}" class="w-50 btn btn-light">
                            <i class="feather-arrow-left me-2"></i>
                            <span>Voltar</span>
                        </a>
                        @can('servico.editar')
                            <a href="{{ route('servicos.edit', $servico) }}" class="w-50 btn btn-primary">
                                <i class="feather-edit me-2"></i>
                                <span>Editar</span>
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        {{-- Coluna direita: agendamentos + vendas pacote (se aplicavel) --}}
        <div class="col-xxl-8 col-xl-7">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title">Últimos Agendamentos</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Atendente</th>
                                    <th>Quando</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($agendamentos as $agendamento)
                                    <tr>
                                        <td>{{ $agendamento->cliente->nome ?? '-' }}</td>
                                        <td>{{ $agendamento->atendente->nome ?? '-' }}</td>
                                        <td>
                                            {{ $agendamento->inicio?->format('d/m/Y H:i') ?? '-' }}
                                            @if ($agendamento->fim)
                                                <small class="text-muted d-block">até {{ $agendamento->fim->format('H:i') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $agendamento->status->cor() }}">
                                                {{ $agendamento->status->label() }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">Nenhum agendamento registrado.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($agendamentos->hasPages())
                    <div class="card-footer">
                        {{ $agendamentos->links() }}
                    </div>
                @endif
            </div>

            @if ($vendasPacote)
                <div class="card stretch stretch-full mt-4">
                    <div class="card-header">
                        <h5 class="card-title">Vendas deste Pacote</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Cliente</th>
                                        <th>Data</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($vendasPacote as $venda)
                                        <tr>
                                            <td>#{{ $venda->id }}</td>
                                            <td>{{ $venda->cliente->nome ?? '-' }}</td>
                                            <td>{{ $venda->created_at->format('d/m/Y') }}</td>
                                            <td>
                                                <span class="badge bg-{{ $venda->status->cor() }}">
                                                    {{ $venda->status->label() }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-4">Nenhuma venda registrada.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if ($vendasPacote->hasPages())
                        <div class="card-footer">
                            {{ $vendasPacote->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection
