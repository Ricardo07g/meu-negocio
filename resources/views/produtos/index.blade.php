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

    {{-- Card with table --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Quantidade</th>
                            <th>Valor</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($produtos as $produto)
                        <tr>
                            <td>{{ $produto->nome }}</td>
                            <td>{{ $produto->quantidade }}</td>
                            <td>R$ {{ number_format($produto->valor, 2, ',', '.') }}</td>
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
                        <tr><td colspan="4" class="text-center text-muted py-4">Nenhum produto cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
