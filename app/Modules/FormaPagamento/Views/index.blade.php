@extends('layouts.app')

@section('titulo', 'Formas de Pagamento - Meu Negócio')
@section('titulo-pagina', 'Formas de Pagamento')
@section('breadcrumb')
    <li class="breadcrumb-item active">Formas de Pagamento</li>
@endsection

@section('content')
    @php $multiEmpresa = count((array) session('empresas_atuais', [])) > 1; @endphp

    @can('forma_pagamento.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('formas-pagamento.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Nova Forma
            </a>
        </div>
    </div>
    @endcan

    {{-- Filtros --}}
    <div class="card stretch stretch-full mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('formas-pagamento.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="Nome da forma..." value="{{ request('q') }}">
                    </div>
                    @include('partials.filtro-empresa-listagem', ['modo' => 'embed', 'colunaCss' => 'col-12 col-sm-6 col-md-3'])
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos</option>
                            @foreach($tipos as $tipo)
                                <option value="{{ $tipo->value }}" @selected(request('tipo') === $tipo->value)>{{ $tipo->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="ativo" class="form-select">
                            <option value="">Todos</option>
                            <option value="1" @selected(request('ativo') === '1')>Ativa</option>
                            <option value="0" @selected(request('ativo') === '0')>Inativa</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('formas-pagamento.index') }}" class="btn btn-light" title="Limpar filtros">
                            <i class="feather-x me-1"></i>Limpar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-filter me-1"></i>Filtrar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Card with table --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            @if($multiEmpresa)<th>Empresa</th>@endif
                            <th>Tipo</th>
                            <th>Destino</th>
                            <th class="text-center">Liquidação</th>
                            <th class="text-end">Taxa</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($formas as $forma)
                        <tr>
                            <td>{{ $forma->nome }}</td>
                            @if($multiEmpresa)<td class="text-muted">{{ $forma->empresa?->nome ?? '—' }}</td>@endif
                            <td><span class="badge bg-soft-secondary text-secondary">{{ $forma->tipo->label() }}</span></td>
                            <td>
                                @if($forma->gera_recebivel)
                                    <span class="badge bg-soft-info text-info" title="Vira recebível do banco/adquirente, não entra na gaveta do caixa">Recebível</span>
                                @else
                                    <span class="badge bg-soft-success text-success" title="Entra na gaveta do caixa na hora">Caixa</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($forma->dias_liquidacao > 0)
                                    D+{{ $forma->dias_liquidacao }}
                                @else
                                    <span class="text-muted">Imediata</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($forma->taxas_count > 0)
                                    <span class="text-muted fs-12">por faixa</span>
                                @else
                                    {{ number_format((float) $forma->taxa_percentual, 2, ',', '.') }}%
                                @endif
                            </td>
                            <td>
                                @if($forma->ativo)
                                    <span class="badge bg-soft-success text-success">Ativa</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger">Inativa</span>
                                @endif
                            </td>
                            <td>
                                <div class="hstack gap-2 justify-content-end">
                                    <div class="dropdown">
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            @can('forma_pagamento.editar')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('formas-pagamento.edit', $forma) }}">
                                                    <i class="feather-edit-3 me-3"></i>
                                                    <span>Editar</span>
                                                </a>
                                            </li>
                                            @endcan
                                            @can('forma_pagamento.excluir')
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('formas-pagamento.destroy', $forma) }}" method="POST" data-confirm="Excluir esta forma de pagamento?">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="feather-trash-2 me-3"></i>
                                                        <span>Excluir</span>
                                                    </button>
                                                </form>
                                            </li>
                                            @endcan
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ $multiEmpresa ? 8 : 7 }}" class="text-center text-muted py-4">Nenhuma forma de pagamento cadastrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
