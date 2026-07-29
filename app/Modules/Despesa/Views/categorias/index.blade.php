@extends('layouts.app')

@section('titulo', 'Categorias de Despesa - Meu Negócio')
@section('titulo-pagina', 'Categorias de Despesa')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('despesas.index') }}">Contas a Pagar</a></li>
    <li class="breadcrumb-item active">Categorias</li>
@endsection

@section('content')
    <x-botao-novo :rota="route('categorias-despesa.create')" label="Nova Categoria" permissao="categoria_despesa.criar" />

    {{-- Filtros --}}
    <x-filtros-listagem :action="route('categorias-despesa.index')">
        <div class="col-12">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control" placeholder="Descrição da categoria..." value="{{ request('q') }}">
        </div>

        <div class="col-12 col-sm-6">
            <label class="form-label">
                Status
                <x-label-info content="<b>Ativa</b>: categoria disponível para seleção em novas despesas.<br><b>Inativa</b>: categoria oculta no cadastro — despesas existentes mantêm o vínculo, mas ela não aparece em novos registros." />
            </label>
            <select name="ativo" class="form-select">
                <option value="">Todos</option>
                <option value="1" @selected(request('ativo') === '1')>Ativa</option>
                <option value="0" @selected(request('ativo') === '0')>Inativa</option>
            </select>
        </div>
        <div class="col-12 col-sm-6">
            <label class="form-label">
                Despesas vinculadas
                <x-label-info content="<b>Com despesas</b>: categorias que já têm ao menos uma despesa cadastrada.<br><b>Sem despesas</b>: categorias vazias (útil para identificar categorias não usadas)." />
            </label>
            <select name="com_despesas" class="form-select">
                <option value="">Todas</option>
                <option value="com" @selected(request('com_despesas') === 'com')>Com despesas</option>
                <option value="sem" @selected(request('com_despesas') === 'sem')>Sem despesas</option>
            </select>
        </div>
    </x-filtros-listagem>

    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover tabela-empilha mb-0">
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th class="text-center">Despesas</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categorias as $categoria)
                        <tr>
                            <td data-label="Descrição">{{ $categoria->descricao }}</td>
                            <td class="text-center" data-label="Despesas">
                                @if($categoria->despesas_count > 0)
                                    <span class="badge bg-soft-info text-info">{{ $categoria->despesas_count }}</span>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td data-label="Status">
                                @if($categoria->ativo)
                                    <span class="badge bg-soft-success text-success">Ativa</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger">Inativa</span>
                                @endif
                            </td>
                            <td>
                                <x-acoes-linha :editar="route('categorias-despesa.edit', $categoria)"
                                               permissaoEditar="categoria_despesa.editar"
                                               :excluir="route('categorias-despesa.destroy', $categoria)"
                                               permissaoExcluir="categoria_despesa.excluir"
                                               confirmacao="Excluir esta categoria?" />
                            </td>
                        </tr>
                        @empty
                        <tr class="sem-registros"><td colspan="4" class="text-center text-muted py-4">Nenhuma categoria cadastrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
