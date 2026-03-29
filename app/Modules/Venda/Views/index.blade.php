@extends('layouts.app')

@section('titulo', 'Vendas - Meu Negócio')
@section('titulo-pagina', 'Vendas')
@section('breadcrumb')
    <li class="breadcrumb-item active">Vendas</li>
@endsection

@section('content')
    {{-- Button row OUTSIDE the card --}}
    @can('agendamento.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('vendas.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Nova Venda
            </a>
        </div>
    </div>
    @endcan

    {{-- Card with table --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Serviço</th>
                            <th>Tipo</th>
                            <th>Info</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vendas as $venda)
                        <tr>
                            <td>{{ $venda->cliente }}</td>
                            <td>{{ $venda->servico }}</td>
                            <td>
                                @switch($venda->tipo)
                                    @case('avulso') <span class="badge bg-light text-dark">Avulso</span> @break
                                    @case('pacote') <span class="badge bg-primary">Pacote</span> @break
                                    @case('produto') <span class="badge bg-warning">Produto</span> @break
                                @endswitch
                            </td>
                            <td>{{ $venda->info }}</td>
                            <td>
                                @switch($venda->status)
                                    @case('agendado')
                                        <span class="badge bg-info">Agendado</span>
                                        @break
                                    @case('confirmado')
                                        <span class="badge bg-primary">Confirmado</span>
                                        @break
                                    @case('finalizado')
                                        <span class="badge bg-success">Finalizado</span>
                                        @break
                                    @case('cancelado')
                                        <span class="badge bg-danger">Cancelado</span>
                                        @break
                                    @case('ativo')
                                        <span class="badge bg-success">Ativo</span>
                                        @break
                                    @case('concluido')
                                        <span class="badge bg-primary">Concluído</span>
                                        @break
                                    @default
                                        <span class="badge bg-secondary">{{ ucfirst($venda->status) }}</span>
                                @endswitch
                            </td>
                            <td>{{ $venda->data }}</td>
                            <td>
                                <div class="hstack gap-2 justify-content-end">
                                    <div class="dropdown">
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                @if($venda->tipo === 'avulso')
                                                <a class="dropdown-item" href="{{ route('vendas.show-avulso', $venda->id) }}">
                                                    <i class="feather-eye me-3"></i><span>Ver</span>
                                                </a>
                                                @elseif($venda->tipo === 'pacote')
                                                <a class="dropdown-item" href="{{ route('vendas.show-pacote', $venda->id) }}">
                                                    <i class="feather-eye me-3"></i><span>Ver</span>
                                                </a>
                                                @else
                                                <a class="dropdown-item" href="{{ route('vendas.show-produto', $venda->id) }}">
                                                    <i class="feather-eye me-3"></i><span>Ver</span>
                                                </a>
                                                @endif
                                            </li>
                                            @if(!in_array($venda->status, ['cancelado', 'finalizado', 'concluido']))
                                            @can('agendamento.cancelar')
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                @if($venda->tipo === 'avulso')
                                                <form action="{{ route('vendas.cancelar-avulso', $venda->id) }}" method="POST" data-confirm="Cancelar esta venda?">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="feather-x-circle me-3"></i>
                                                        <span>Cancelar</span>
                                                    </button>
                                                </form>
                                                @else
                                                <form action="{{ route('vendas.cancelar-pacote', $venda->id) }}" method="POST" data-confirm="Cancelar este pacote e todos agendamentos pendentes?">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="feather-x-circle me-3"></i>
                                                        <span>Cancelar</span>
                                                    </button>
                                                </form>
                                                @endif
                                            </li>
                                            @endcan
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhuma venda registrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
