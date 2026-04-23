@extends('layouts.app')

@section('titulo', 'Produtos - Meu Negócio')
@section('titulo-pagina', 'Produtos')
@section('breadcrumb')
    <li class="breadcrumb-item active">Produtos</li>
@endsection

@section('content')
    {{-- Button row OUTSIDE the card --}}
    @can('produto.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('produtos.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Novo Produto
            </a>
        </div>
    </div>
    @endcan

    {{-- Filtros --}}
    <div class="card stretch stretch-full mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('produtos.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-12">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="Nome, código, código de barras ou descrição..." value="{{ request('q') }}">
                    </div>

                    <div class="col-md-4">
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
                    <div class="col-md-4">
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
                    <div class="col-md-4">
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

                    <div class="col-md-3">
                        <label class="form-label">Preço mínimo</label>
                        <input type="number" step="0.01" min="0" name="preco_min" class="form-control" placeholder="0,00" value="{{ request('preco_min') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Preço máximo</label>
                        <input type="number" step="0.01" min="0" name="preco_max" class="form-control" placeholder="0,00" value="{{ request('preco_max') }}">
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('produtos.index') }}" class="btn btn-light" title="Limpar filtros">
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
                            <td>{{ $produto->codigo ?? '-' }}</td>
                            <td>{{ $produto->nome }}</td>
                            <td>{{ $produto->categoria->descricao ?? '-' }}</td>
                            <td>
                                @if($produto->estoque_minimo !== null && $produto->quantidade <= $produto->estoque_minimo)
                                    <span class="text-danger fw-bold">{{ $produto->quantidade }}</span>
                                    <i class="feather-alert-triangle text-danger ms-1" title="Estoque baixo"></i>
                                @else
                                    {{ $produto->quantidade }}
                                @endif
                            </td>
                            <td>R$ {{ number_format($produto->valor_venda, 2, ',', '.') }}</td>
                            <td>
                                @if($produto->ativo)
                                    <span class="badge bg-success">Ativo</span>
                                @else
                                    <span class="badge bg-danger">Inativo</span>
                                @endif
                            </td>
                            <td>
                                <div class="hstack gap-2 justify-content-end">
                                    <div class="dropdown">
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('produtos.show', $produto) }}">
                                                    <i class="feather-eye me-3"></i>
                                                    <span>Ver</span>
                                                </a>
                                            </li>
                                            @can('produto.editar')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('produtos.edit', $produto) }}">
                                                    <i class="feather-edit-3 me-3"></i>
                                                    <span>Editar</span>
                                                </a>
                                            </li>
                                            @endcan
                                            @can('produto.excluir')
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('produtos.destroy', $produto) }}" method="POST" data-confirm="Excluir este produto?">
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
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum produto cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($produtos->hasPages())
            <div class="card-footer">
                {{ $produtos->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
