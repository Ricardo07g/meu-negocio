@extends('layouts.app')

@section('titulo', 'Produtos - Meu Negócio')
@section('titulo-pagina', 'Produtos')
@section('breadcrumb')
    <li class="breadcrumb-item active">Produtos</li>
@endsection

@section('content')
    {{-- Button row OUTSIDE the card --}}
    <x-botao-novo :rota="route('produtos.create')" label="Novo Produto" permissao="produto.criar" />

    {{-- Filtros --}}
    <x-filtros-listagem :action="route('produtos.index')">
        <div class="col-12">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control" placeholder="Nome, código, código de barras ou descrição..." value="{{ request('q') }}">
        </div>

        <div class="col-12 col-sm-6 col-md-4">
            <label class="form-label">Categoria</label>
            <select name="categoria_produto_id" class="form-select">
                <option value="">Todas</option>
                @foreach($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected((int) request('categoria_produto_id') === $categoria->id)>
                        {{ $categoria->descricao }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-4">
            <label class="form-label">
                Status
                <x-label-info content="<b>Ativo</b>: produto disponível para venda e busca.<br><b>Inativo</b>: produto oculto no catálogo — não aparece em novas vendas, mas o histórico é mantido." />
            </label>
            <select name="ativo" class="form-select">
                <option value="">Todos</option>
                <option value="1" @selected(request('ativo') === '1')>Ativo</option>
                <option value="0" @selected(request('ativo') === '0')>Inativo</option>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-4">
            <label class="form-label">
                Estoque
                <x-label-info content="<b>Disponível</b>: quantidade em estoque maior que zero.<br><b>Estoque baixo</b>: quantidade atingiu ou ficou abaixo do estoque mínimo definido.<br><b>Zerado</b>: sem unidades em estoque." />
            </label>
            <select name="estoque" class="form-select">
                <option value="">Todos</option>
                <option value="disponivel" @selected(request('estoque') === 'disponivel')>Disponível (&gt; 0)</option>
                <option value="baixo" @selected(request('estoque') === 'baixo')>Estoque baixo (&le; mínimo)</option>
                <option value="zerado" @selected(request('estoque') === 'zerado')>Zerado</option>
            </select>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">Preço mínimo</label>
            <input type="number" step="0.01" min="0" name="preco_min" class="form-control" placeholder="0,00" value="{{ request('preco_min') }}">
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">Preço máximo</label>
            <input type="number" step="0.01" min="0" name="preco_max" class="form-control" placeholder="0,00" value="{{ request('preco_max') }}">
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
                            <th>Código</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Qtd</th>
                            <th>Valor Venda</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($produtos as $produto)
                        <tr>
                            <td><x-thumb :url="$produto->imagem_thumb_url" :nome="$produto->nome" icone="feather-package" :circulo="false" /></td>
                            <td data-label="Código">{{ $produto->codigo ?? '-' }}</td>
                            <td data-label="Nome">{{ $produto->nome }}</td>
                            <td data-label="Categoria">{{ $produto->categoria->descricao ?? '-' }}</td>
                            <td data-label="Qtd">
                                @if($produto->estoque_minimo !== null && $produto->quantidade <= $produto->estoque_minimo)
                                    <span class="text-danger fw-bold">{{ $produto->quantidade }}</span>
                                    <i class="feather-alert-triangle text-danger ms-1" title="Estoque baixo"></i>
                                @else
                                    {{ $produto->quantidade }}
                                @endif
                            </td>
                            <td data-label="Valor Venda">R$ {{ number_format($produto->valor_venda, 2, ',', '.') }}</td>
                            <td data-label="Status">
                                @if($produto->ativo)
                                    <span class="badge bg-success">Ativo</span>
                                @else
                                    <span class="badge bg-danger">Inativo</span>
                                @endif
                            </td>
                            <td>
                                <x-acoes-linha :ver="route('produtos.show', $produto)"
                                               :editar="route('produtos.edit', $produto)"
                                               permissaoEditar="produto.editar"
                                               :excluir="route('produtos.destroy', $produto)"
                                               permissaoExcluir="produto.excluir"
                                               confirmacao="Excluir este produto?" />
                            </td>
                        </tr>
                        @empty
                        <tr class="sem-registros"><td colspan="8" class="text-center text-muted py-4">Nenhum produto cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <x-paginacao :paginator="$produtos" />
    </div>
@endsection
