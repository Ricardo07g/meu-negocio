@extends('layouts.app')

@section('titulo', 'Serviços - Meu Negócio')
@section('titulo-pagina', 'Serviços')
@section('breadcrumb')
    <li class="breadcrumb-item active">Serviços</li>
@endsection

@section('content')
    {{-- Button row OUTSIDE the card --}}
    @can('servico.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('servicos.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Novo Serviço
            </a>
        </div>
    </div>
    @endcan

    {{-- Filtros --}}
    <div class="card stretch stretch-full mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('servicos.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-12">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="Nome ou descrição..." value="{{ request('q') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos</option>
                            <option value="unico" @selected(request('tipo') === 'unico')>Serviço Único</option>
                            <option value="etapas" @selected(request('tipo') === 'etapas')>Serviço em Etapas</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor mínimo</label>
                        <input type="number" step="0.01" min="0" name="valor_min" class="form-control" placeholder="0,00" value="{{ request('valor_min') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor máximo</label>
                        <input type="number" step="0.01" min="0" name="valor_max" class="form-control" placeholder="0,00" value="{{ request('valor_max') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Duração mínima (min)</label>
                        <input type="number" min="0" name="duracao_min" class="form-control" placeholder="0" value="{{ request('duracao_min') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Duração máxima (min)</label>
                        <input type="number" min="0" name="duracao_max" class="form-control" placeholder="0" value="{{ request('duracao_max') }}">
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('servicos.index') }}" class="btn btn-light" title="Limpar filtros">
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
                            <th style="width:56px"></th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Duração (min)</th>
                            <th>Valor</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($servicos as $servico)
                        <tr>
                            <td><x-thumb :url="$servico->imagem_thumb_url" :nome="$servico->nome" icone="feather-scissors" :circulo="false" /></td>
                            <td>{{ $servico->nome }}</td>
                            <td>
                                @switch($servico->tipo->value)
                                    @case('etapas')
                                        <span class="badge bg-primary">Etapas ({{ $servico->qtd_etapas }}x)</span>
                                        @break
                                    @default
                                        <span class="badge bg-light text-dark">Único</span>
                                @endswitch
                            </td>
                            <td>{{ $servico->duracao }} min</td>
                            <td>R$ {{ number_format($servico->valor, 2, ',', '.') }}</td>
                            <td>
                                <div class="hstack gap-2 justify-content-end">
                                    <div class="dropdown">
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('servicos.show', $servico) }}">
                                                    <i class="feather-eye me-3"></i>
                                                    <span>Ver</span>
                                                </a>
                                            </li>
                                            @can('servico.editar')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('servicos.edit', $servico) }}">
                                                    <i class="feather-edit-3 me-3"></i>
                                                    <span>Editar</span>
                                                </a>
                                            </li>
                                            @endcan
                                            @can('servico.excluir')
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('servicos.destroy', $servico) }}" method="POST" data-confirm="Excluir este serviço?">
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
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum serviço cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($servicos->hasPages())
            <div class="card-footer">
                {{ $servicos->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
