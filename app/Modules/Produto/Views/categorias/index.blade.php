@extends('layouts.app')

@section('titulo', 'Categorias de Produto - Meu Negócio')
@section('titulo-pagina', 'Categorias de Produto')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('produtos.index') }}">Produtos</a></li>
    <li class="breadcrumb-item active">Categorias</li>
@endsection

@section('content')
    {{-- Button row OUTSIDE the card --}}
    @can('produto.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('categorias-produto.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Nova Categoria
            </a>
        </div>
    </div>
    @endcan

    {{-- Filtros --}}
    <div class="card stretch stretch-full mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('categorias-produto.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-12">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="Descrição da categoria..." value="{{ request('q') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="ativo" class="form-select">
                            <option value="">Todos</option>
                            <option value="1" @selected(request('ativo') === '1')>Ativa</option>
                            <option value="0" @selected(request('ativo') === '0')>Inativa</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Produtos vinculados</label>
                        <select name="com_produtos" class="form-select">
                            <option value="">Todas</option>
                            <option value="com" @selected(request('com_produtos') === 'com')>Com produtos</option>
                            <option value="sem" @selected(request('com_produtos') === 'sem')>Sem produtos</option>
                        </select>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('categorias-produto.index') }}" class="btn btn-light" title="Limpar filtros">
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
                            <th>Descrição</th>
                            <th class="text-center">Produtos</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categorias as $categoria)
                        <tr>
                            <td>{{ $categoria->descricao }}</td>
                            <td class="text-center">
                                @if($categoria->produtos_count > 0)
                                    <span class="badge bg-soft-info text-info">{{ $categoria->produtos_count }}</span>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td>
                                @if($categoria->ativo)
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
                                            @can('produto.editar')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('categorias-produto.edit', $categoria) }}">
                                                    <i class="feather-edit-3 me-3"></i>
                                                    <span>Editar</span>
                                                </a>
                                            </li>
                                            @endcan
                                            @can('produto.excluir')
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('categorias-produto.destroy', $categoria) }}" method="POST" data-confirm="Excluir esta categoria?">
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
                        <tr><td colspan="4" class="text-center text-muted py-4">Nenhuma categoria cadastrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
