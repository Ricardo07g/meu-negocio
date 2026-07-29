@extends('layouts.app')

@section('titulo', 'Formas de Pagamento - Meu Negócio')
@section('titulo-pagina', 'Formas de Pagamento')
@section('breadcrumb')
    <li class="breadcrumb-item active">Formas de Pagamento</li>
@endsection

@section('content')
    @php $multiEmpresa = count((array) session('empresas_atuais', [])) > 1; @endphp

    <x-botao-novo :rota="route('formas-pagamento.create')" label="Nova Forma" permissao="forma_pagamento.criar" />

    {{-- Filtros --}}
    <x-filtros-listagem :action="route('formas-pagamento.index')">
        <div class="col-12 col-md-4">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control" placeholder="Nome da forma..." value="{{ request('q') }}">
        </div>
        @include('partials.filtro-empresa-listagem', ['modo' => 'embed', 'colunaCss' => 'col-12 col-sm-6 col-md-3'])
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select">
                <option value="">Todos</option>
                @foreach($tipos as $tipo)
                    <option value="{{ $tipo->value }}" @selected(request('tipo') === $tipo->value)>{{ $tipo->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-2">
            <label class="form-label">Status</label>
            <select name="ativo" class="form-select">
                <option value="">Todos</option>
                <option value="1" @selected(request('ativo') === '1')>Ativa</option>
                <option value="0" @selected(request('ativo') === '0')>Inativa</option>
            </select>
        </div>
    </x-filtros-listagem>

    {{-- Card with table --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover tabela-empilha mb-0">
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
                            <td data-label="Nome">{{ $forma->nome }}</td>
                            @if($multiEmpresa)<td class="text-muted" data-label="Empresa">{{ $forma->empresa?->nome ?? '—' }}</td>@endif
                            <td data-label="Tipo"><span class="badge bg-soft-secondary text-secondary">{{ $forma->tipo->label() }}</span></td>
                            <td data-label="Destino">
                                @if($forma->gera_recebivel)
                                    <span class="badge bg-soft-info text-info" title="Vira recebível do banco/adquirente, não entra na gaveta do caixa">Recebível</span>
                                @else
                                    <span class="badge bg-soft-success text-success" title="Entra na gaveta do caixa na hora">Caixa</span>
                                @endif
                            </td>
                            <td class="text-center" data-label="Liquidação">
                                @if($forma->dias_liquidacao > 0)
                                    D+{{ $forma->dias_liquidacao }}
                                @else
                                    <span class="text-muted">Imediata</span>
                                @endif
                            </td>
                            <td class="text-end" data-label="Taxa">
                                @if($forma->taxas_count > 0)
                                    <span class="text-muted fs-12">por faixa</span>
                                @else
                                    {{ number_format((float) $forma->taxa_percentual, 2, ',', '.') }}%
                                @endif
                            </td>
                            <td data-label="Status">
                                @if($forma->ativo)
                                    <span class="badge bg-soft-success text-success">Ativa</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger">Inativa</span>
                                @endif
                            </td>
                            <td>
                                <x-acoes-linha :editar="route('formas-pagamento.edit', $forma)"
                                               permissaoEditar="forma_pagamento.editar"
                                               :excluir="route('formas-pagamento.destroy', $forma)"
                                               permissaoExcluir="forma_pagamento.excluir"
                                               confirmacao="Excluir esta forma de pagamento?" />
                            </td>
                        </tr>
                        @empty
                        <tr class="sem-registros"><td colspan="{{ $multiEmpresa ? 8 : 7 }}" class="text-center text-muted py-4">Nenhuma forma de pagamento cadastrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
