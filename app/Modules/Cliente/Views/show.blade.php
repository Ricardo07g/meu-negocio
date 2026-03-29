@extends('layouts.app')

@section('titulo', 'Cliente - Meu Negócio')
@section('titulo-pagina', $cliente->nome)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('clientes.index') }}">Clientes</a></li>
    <li class="breadcrumb-item active">{{ $cliente->nome }}</li>
@endsection

@section('content')
    <div class="row">
        {{-- Coluna esquerda: Perfil do cliente --}}
        <div class="col-xxl-4 col-xl-6">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="mb-4 text-center">
                        <div class="wd-80 ht-80 mx-auto mb-3">
                            <div class="avatar-text avatar-xl bg-primary text-white rounded-circle fs-24 fw-bold">
                                {{ mb_substr($cliente->nome, 0, 1) }}
                            </div>
                        </div>
                        <h5 class="fw-bold mb-1">{{ $cliente->nome }}</h5>
                        @if($cliente->email)
                        <span class="fs-12 text-muted">{{ $cliente->email }}</span>
                        @endif
                    </div>

                    <ul class="list-unstyled mb-4">
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3"><i class="feather-phone"></i>Telefone</span>
                            <span>{{ $cliente->telefone ?? '-' }}</span>
                        </li>
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3"><i class="feather-mail"></i>Email</span>
                            <span>{{ $cliente->email ?? '-' }}</span>
                        </li>
                        <li class="hstack justify-content-between mb-0">
                            <span class="text-muted fw-medium hstack gap-3"><i class="feather-file-text"></i>Obs.</span>
                            <span>{{ $cliente->observacoes ?? '-' }}</span>
                        </li>
                    </ul>

                    <div class="d-flex gap-2 text-center pt-4">
                        @can('cliente.excluir')
                        <form action="{{ route('clientes.destroy', $cliente) }}" method="POST" class="w-50" data-confirm="Excluir este cliente?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-light-brand w-100">
                                <i class="feather-trash-2 me-2"></i>
                                <span>Excluir</span>
                            </button>
                        </form>
                        @endcan
                        @can('cliente.editar')
                        <a href="{{ route('clientes.edit', $cliente) }}" class="w-50 btn btn-primary">
                            <i class="feather-edit me-2"></i>
                            <span>Editar</span>
                        </a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        {{-- Coluna direita: Abas --}}
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="clienteTabs" role="tablist">
                        <li class="nav-item flex-fill border-top" role="presentation">
                            <a href="javascript:void(0);" class="nav-link active" data-bs-toggle="tab" data-bs-target="#pacotesTab" role="tab">
                                <i class="feather-package me-2"></i>Pacotes
                            </a>
                        </li>
                        <li class="nav-item flex-fill border-top" role="presentation">
                            <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab" data-bs-target="#agendamentosTab" role="tab">
                                <i class="feather-calendar me-2"></i>Agendamentos
                            </a>
                        </li>
                        <li class="nav-item flex-fill border-top" role="presentation">
                            <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab" data-bs-target="#pagamentosTab" role="tab">
                                <i class="feather-dollar-sign me-2"></i>Pagamentos
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="tab-content">
                    {{-- Aba Pacotes --}}
                    <div class="tab-pane fade show active p-0" id="pacotesTab" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Serviço</th>
                                        <th>Profissional</th>
                                        <th>Valor Total</th>
                                        <th>Sessões</th>
                                        <th>Status</th>
                                        <th>Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cliente->vendasPacote->sortByDesc('created_at') as $venda)
                                    <tr>
                                        <td>{{ $venda->servico->nome }}</td>
                                        <td>{{ $venda->profissional->usuario->nome }}</td>
                                        <td>R$ {{ number_format($venda->valor_total, 2, ',', '.') }}</td>
                                        <td>{{ $venda->sessoesRealizadas() }}/{{ $venda->qtd_sessoes }}</td>
                                        <td>
                                            @switch($venda->status->value)
                                                @case('ativo') <span class="badge bg-success">Ativo</span> @break
                                                @case('concluido') <span class="badge bg-primary">Concluído</span> @break
                                                @case('cancelado') <span class="badge bg-danger">Cancelado</span> @break
                                            @endswitch
                                        </td>
                                        <td>{{ $venda->created_at->format('d/m/Y') }}</td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">Nenhum pacote contratado.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Aba Agendamentos --}}
                    <div class="tab-pane fade p-0" id="agendamentosTab" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Serviço</th>
                                        <th>Profissional</th>
                                        <th>Data</th>
                                        <th>Horário</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cliente->agendamentos->sortByDesc('inicio') as $ag)
                                    <tr>
                                        <td>{{ $ag->servico->nome }}</td>
                                        <td>{{ $ag->profissional->usuario->nome }}</td>
                                        <td>{{ $ag->inicio->format('d/m/Y') }}</td>
                                        <td>{{ $ag->inicio->format('H:i') }} - {{ $ag->fim->format('H:i') }}</td>
                                        <td>
                                            @switch($ag->status->value)
                                                @case('agendado') <span class="badge bg-info">Agendado</span> @break
                                                @case('confirmado') <span class="badge bg-primary">Confirmado</span> @break
                                                @case('finalizado') <span class="badge bg-success">Finalizado</span> @break
                                                @case('cancelado') <span class="badge bg-danger">Cancelado</span> @break
                                            @endswitch
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">Nenhum agendamento encontrado.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Aba Pagamentos --}}
                    <div class="tab-pane fade p-0" id="pagamentosTab" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Serviço</th>
                                        <th>Valor</th>
                                        <th>Forma</th>
                                        <th>Status</th>
                                        <th>Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cliente->pagamentos->sortByDesc('created_at') as $pagamento)
                                    <tr>
                                        <td>{{ $pagamento->agendamento->servico->nome ?? '-' }}</td>
                                        <td>R$ {{ number_format($pagamento->valor, 2, ',', '.') }}</td>
                                        <td>{{ ucfirst($pagamento->forma_pagamento->value) }}</td>
                                        <td>
                                            @switch($pagamento->status->value)
                                                @case('pago') <span class="badge bg-success">Pago</span> @break
                                                @case('pendente') <span class="badge bg-warning">Pendente</span> @break
                                                @case('cancelado') <span class="badge bg-danger">Cancelado</span> @break
                                                @case('estornado') <span class="badge bg-secondary">Estornado</span> @break
                                            @endswitch
                                        </td>
                                        <td>{{ $pagamento->created_at->format('d/m/Y') }}</td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">Nenhum pagamento encontrado.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
