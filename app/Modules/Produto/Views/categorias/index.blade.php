@extends('layouts.app')

@section('titulo', 'Categorias de Produto - Meu Negócio')
@section('titulo-pagina', 'Categorias de Produto')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('produtos.index') }}">Produtos</a></li>
    <li class="breadcrumb-item active">Categorias</li>
@endsection

@section('content')
    {{-- Button row OUTSIDE the card --}}
    <x-botao-novo :rota="route('categorias-produto.create')" label="Nova Categoria" permissao="produto.criar" />

    {{-- Filtros --}}
    <x-filtros-listagem :action="route('categorias-produto.index')">
        <div class="col-12">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control" placeholder="Descrição da categoria..." value="{{ request('q') }}">
        </div>

        <div class="col-12 col-sm-6">
            <label class="form-label">
                Status
                <x-label-info content="<b>Ativa</b>: categoria disponível para seleção em novos produtos.<br><b>Inativa</b>: categoria oculta no cadastro — produtos existentes mantêm o vínculo, mas a categoria não aparece para novas atribuições." />
            </label>
            <select name="ativo" class="form-select">
                <option value="">Todos</option>
                <option value="1" @selected(request('ativo') === '1')>Ativa</option>
                <option value="0" @selected(request('ativo') === '0')>Inativa</option>
            </select>
        </div>
        <div class="col-12 col-sm-6">
            <label class="form-label">
                Produtos vinculados
                <x-label-info content="<b>Com produtos</b>: categorias que já têm ao menos um produto cadastrado.<br><b>Sem produtos</b>: categorias vazias (útil para identificar cadastros que podem ser removidos ou reorganizados)." />
            </label>
            <select name="com_produtos" class="form-select">
                <option value="">Todas</option>
                <option value="com" @selected(request('com_produtos') === 'com')>Com produtos</option>
                <option value="sem" @selected(request('com_produtos') === 'sem')>Sem produtos</option>
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
                            <th>Descrição</th>
                            <th class="text-center">Produtos</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categorias as $categoria)
                        <tr>
                            <td data-label="Descrição">{{ $categoria->descricao }}</td>
                            <td class="text-center" data-label="Produtos">
                                @if($categoria->produtos_count > 0)
                                    <span class="badge bg-soft-info text-info">{{ $categoria->produtos_count }}</span>
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
                                <x-acoes-linha :editar="route('categorias-produto.edit', $categoria)"
                                               permissaoEditar="produto.editar"
                                               :excluir="route('categorias-produto.destroy', $categoria)"
                                               permissaoExcluir="produto.excluir"
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
