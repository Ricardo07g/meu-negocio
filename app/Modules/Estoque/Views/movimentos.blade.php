@extends('layouts.app')

@section('titulo', 'Movimentos de Estoque - Meu Negócio')
@section('titulo-pagina', 'Movimentos de Estoque')
@section('breadcrumb')
    <li class="breadcrumb-item active">Movimentos de Estoque</li>
@endsection

@section('content')
    {{-- Botao Novo Movimento --}}
    <x-botao-novo :rota="route('movimentos-estoque.create')" label="Novo Movimento" permissao="movimento_estoque.criar" />

    {{-- Filtros --}}
    <x-filtros-listagem :action="route('movimentos-estoque.index')">
        {{-- Linha 1: Empresa (ME-010 v3) + Busca --}}
        @include('partials.filtro-empresa-listagem', ['modo' => 'embed', 'colunaCss' => 'col-12 col-sm-6 col-md-3'])

        <div class="col-12 col-md-9">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control" placeholder="Nome ou código do produto..." value="{{ request('q') }}">
        </div>

        <div class="col-12 col-sm-6 col-md-4">
            <label class="form-label">Produto</label>
            <select name="produto_id" class="form-select">
                <option value="">Todos</option>
                @foreach($produtos as $produto)
                    <option value="{{ $produto->id }}" @selected((int) request('produto_id') === $produto->id)>
                        {{ $produto->nome }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-4">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select">
                <option value="">Todos</option>
                <option value="entrada" @selected(request('tipo') === 'entrada')>Entrada</option>
                <option value="saida" @selected(request('tipo') === 'saida')>Saída</option>
                <option value="ajuste" @selected(request('tipo') === 'ajuste')>Ajuste</option>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-4">
            <label class="form-label">Período</label>
            <select name="periodo_preset" class="form-select">
                <option value="">Todos</option>
                <option value="hoje" @selected(request('periodo_preset') === 'hoje')>Hoje</option>
                <option value="7dias" @selected(request('periodo_preset') === '7dias')>Últimos 7 dias</option>
                <option value="30dias" @selected(request('periodo_preset') === '30dias')>Últimos 30 dias</option>
                <option value="mes_atual" @selected(request('periodo_preset') === 'mes_atual')>Mês atual</option>
            </select>
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label">Data início</label>
            <input type="date" name="data_inicio" class="form-control" value="{{ request('data_inicio') }}">
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label">Data fim</label>
            <input type="date" name="data_fim" class="form-control" value="{{ request('data_fim') }}">
        </div>
    </x-filtros-listagem>

    {{-- Tabela --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover tabela-empilha mb-0">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Tipo</th>
                            <th>Quantidade</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movimentos as $movimento)
                        <tr>
                            <td data-label="Produto">{{ $movimento->produto->nome ?? '-' }}</td>
                            <td data-label="Tipo">
                                @switch($movimento->tipo->value)
                                    @case('entrada')
                                        <span class="badge bg-success">Entrada</span>
                                        @break
                                    @case('saida')
                                        <span class="badge bg-danger">Saída</span>
                                        @break
                                    @case('ajuste')
                                        <span class="badge bg-warning">Ajuste</span>
                                        @break
                                    @default
                                        <span class="badge bg-secondary">{{ ucfirst($movimento->tipo->value) }}</span>
                                @endswitch
                            </td>
                            <td data-label="Quantidade">{{ $movimento->quantidade }}</td>
                            <td data-label="Data">{{ $movimento->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                        @empty
                        <tr class="sem-registros"><td colspan="4" class="text-center text-muted py-4">Nenhum movimento registrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <x-paginacao :paginator="$movimentos" />
    </div>
@endsection
