@extends('layouts.app')

@section('titulo', 'Serviços - Meu Negócio')
@section('titulo-pagina', 'Serviços')
@section('breadcrumb')
    <li class="breadcrumb-item active">Serviços</li>
@endsection

@section('content')
    {{-- Button row OUTSIDE the card --}}
    <x-botao-novo :rota="route('servicos.create')" label="Novo Serviço" permissao="servico.criar" />

    {{-- Filtros --}}
    <x-filtros-listagem :action="route('servicos.index')">
        <div class="col-12">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control" placeholder="Nome ou descrição..." value="{{ request('q') }}">
        </div>

        <div class="col-12 col-sm-6 col-md-4">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select">
                <option value="">Todos</option>
                <option value="unico" @selected(request('tipo') === 'unico')>Serviço Único</option>
                <option value="etapas" @selected(request('tipo') === 'etapas')>Serviço em Etapas</option>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-4">
            <label class="form-label">Valor mínimo</label>
            <input type="number" step="0.01" min="0" name="valor_min" class="form-control" placeholder="0,00" value="{{ request('valor_min') }}">
        </div>
        <div class="col-12 col-sm-6 col-md-4">
            <label class="form-label">Valor máximo</label>
            <input type="number" step="0.01" min="0" name="valor_max" class="form-control" placeholder="0,00" value="{{ request('valor_max') }}">
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label">Duração mínima (min)</label>
            <input type="number" min="0" name="duracao_min" class="form-control" placeholder="0" value="{{ request('duracao_min') }}">
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label">Duração máxima (min)</label>
            <input type="number" min="0" name="duracao_max" class="form-control" placeholder="0" value="{{ request('duracao_max') }}">
        </div>
    </x-filtros-listagem>

    {{-- Card with table --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover tabela-empilha mb-0">
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
                            <td data-label="Nome">{{ $servico->nome }}</td>
                            <td data-label="Tipo">
                                @switch($servico->tipo->value)
                                    @case('etapas')
                                        <span class="badge bg-primary">Etapas ({{ $servico->qtd_etapas }}x)</span>
                                        @break
                                    @default
                                        <span class="badge bg-light text-dark">Único</span>
                                @endswitch
                            </td>
                            <td data-label="Duração (min)">{{ $servico->duracao }} min</td>
                            <td data-label="Valor">R$ {{ number_format($servico->valor, 2, ',', '.') }}</td>
                            <td>
                                <x-acoes-linha :ver="route('servicos.show', $servico)"
                                               :editar="route('servicos.edit', $servico)"
                                               permissaoEditar="servico.editar"
                                               :excluir="route('servicos.destroy', $servico)"
                                               permissaoExcluir="servico.excluir"
                                               confirmacao="Excluir este serviço?" />
                            </td>
                        </tr>
                        @empty
                        <tr class="sem-registros"><td colspan="6" class="text-center text-muted py-4">Nenhum serviço cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <x-paginacao :paginator="$servicos" />
    </div>
@endsection
