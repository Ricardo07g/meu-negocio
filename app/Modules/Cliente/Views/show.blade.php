@extends('layouts.app')

@section('titulo', 'Cliente - Meu Negocio')
@section('titulo-pagina', $cliente->nome)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('clientes.index') }}">Clientes</a></li>
    <li class="breadcrumb-item active">{{ $cliente->nome }}</li>
@endsection

@section('content')
    <div class="row">
        {{-- Coluna esquerda: Perfil do cliente --}}
        <div class="col-xxl-4 col-xl-5">
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
                        {{-- Telefone --}}
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-phone"></i>Telefone
                            </span>
                            <span>
                                {{ $cliente->telefone ?? '-' }}
                                @if($cliente->telefone_whatsapp)
                                    <span class="badge bg-success ms-1">
                                        <i class="feather-message-circle me-1"></i>WhatsApp
                                    </span>
                                @endif
                            </span>
                        </li>
                        {{-- Email --}}
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-mail"></i>Email
                            </span>
                            <span>{{ $cliente->email ?? '-' }}</span>
                        </li>
                        {{-- CPF --}}
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-credit-card"></i>CPF
                            </span>
                            <span>{{ $cliente->cpf ?? '-' }}</span>
                        </li>
                        {{-- Data de Nascimento --}}
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-calendar"></i>Nascimento
                            </span>
                            <span>
                                @if($cliente->data_nascimento)
                                    {{ $cliente->data_nascimento->format('d/m/Y') }}
                                    <small class="text-muted">({{ $cliente->data_nascimento->age }} anos)</small>
                                @else
                                    -
                                @endif
                            </span>
                        </li>
                        {{-- Sexo --}}
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-user"></i>Sexo
                            </span>
                            <span>
                                @if($cliente->sexo == 'M') Masculino
                                @elseif($cliente->sexo == 'F') Feminino
                                @elseif($cliente->sexo == 'outro') Outro
                                @else -
                                @endif
                            </span>
                        </li>
                        {{-- Endereco --}}
                        <li class="mb-0">
                            <span class="text-muted fw-medium hstack gap-3 mb-2">
                                <i class="feather-map-pin"></i>Endereço
                            </span>
                            <span class="fs-13">
                                @if($cliente->logradouro || $cliente->cidade || $cliente->cep)
                                    @if($cliente->logradouro)
                                        {{ $cliente->logradouro }}{{ $cliente->numero ? ', ' . $cliente->numero : '' }}
                                        @if($cliente->complemento) - {{ $cliente->complemento }} @endif
                                        <br>
                                    @endif
                                    @if($cliente->bairro) {{ $cliente->bairro }}<br> @endif
                                    @if($cliente->cidade || $cliente->estado)
                                        {{ $cliente->cidade }}{{ $cliente->estado ? ' - ' . $cliente->estado : '' }}<br>
                                    @endif
                                    @if($cliente->cep) CEP: {{ $cliente->cep }} @endif
                                @else
                                    -
                                @endif
                            </span>
                        </li>
                    </ul>

                    <div class="d-flex gap-2 text-center pt-4">
                        <a href="{{ route('clientes.index') }}" class="w-50 btn btn-light">
                            <i class="feather-arrow-left me-2"></i>
                            <span>Voltar</span>
                        </a>
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
        <div class="col-xxl-8 col-xl-7">
            <div class="card border-top-0">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="clienteTabs" role="tablist">
                        <li class="nav-item flex-fill border-top" role="presentation">
                            <a href="javascript:void(0);" class="nav-link active" data-bs-toggle="tab" data-bs-target="#etapasTab" role="tab">
                                <i class="feather-layers me-2"></i>Serviços em Etapas
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
                    {{-- Aba Servicos em Etapas --}}
                    <div class="tab-pane fade show active p-0" id="etapasTab" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Servico</th>
                                        <th>Atendente</th>
                                        <th>Valor Total</th>
                                        <th>Etapas</th>
                                        <th>Status</th>
                                        <th>Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cliente->vendasEtapas->sortByDesc('created_at') as $venda)
                                    <tr>
                                        <td>{{ $venda->servico->nome }}</td>
                                        <td>{{ $venda->atendente->nome }}</td>
                                        <td>R$ {{ number_format($venda->valor_total, 2, ',', '.') }}</td>
                                        <td>{{ $venda->etapasRealizadas() }}/{{ $venda->qtd_etapas }}</td>
                                        <td>
                                            @switch($venda->status->value)
                                                @case('ativo') <span class="badge bg-success">Ativo</span> @break
                                                @case('concluido') <span class="badge bg-primary">Concluido</span> @break
                                                @case('cancelado') <span class="badge bg-danger">Cancelado</span> @break
                                            @endswitch
                                        </td>
                                        <td>{{ $venda->created_at->format('d/m/Y') }}</td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">Nenhum serviço em etapas contratado.</td></tr>
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
                                        <th>Servico</th>
                                        <th>Atendente</th>
                                        <th>Data</th>
                                        <th>Horario</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cliente->agendamentos->sortByDesc('inicio') as $ag)
                                    <tr>
                                        <td>{{ $ag->servico->nome }}</td>
                                        <td>{{ $ag->atendente->nome }}</td>
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
                                        <th>Origem</th>
                                        <th>Valor total</th>
                                        <th>Condição</th>
                                        <th>Status</th>
                                        <th>Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cliente->pagamentos->sortByDesc('created_at') as $pagamento)
                                    <tr>
                                        <td>{{ $pagamento->agendamento->servico->nome ?? $pagamento->vendaPacote->servico->nome ?? ($pagamento->vendaProduto ? 'Venda de produto' : '-') }}</td>
                                        <td>R$ {{ number_format($pagamento->valor_total, 2, ',', '.') }}</td>
                                        <td>{{ $pagamento->condicao_pagamento->label() }}</td>
                                        <td>
                                            <span class="badge bg-soft-{{ $pagamento->status->cor() }} text-{{ $pagamento->status->cor() }}">{{ $pagamento->status->label() }}</span>
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
