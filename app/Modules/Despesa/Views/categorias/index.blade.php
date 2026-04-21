@extends('layouts.app')

@section('titulo', 'Categorias de Despesa - Meu Negócio')
@section('titulo-pagina', 'Categorias de Despesa')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('despesas.index') }}">Contas a Pagar</a></li>
    <li class="breadcrumb-item active">Categorias</li>
@endsection

@section('content')
    @can('categoria_despesa.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('categorias-despesa.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Nova Categoria
            </a>
        </div>
    </div>
    @endcan

    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categorias as $categoria)
                        <tr>
                            <td>{{ $categoria->descricao }}</td>
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
                                            @can('categoria_despesa.editar')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('categorias-despesa.edit', $categoria) }}">
                                                    <i class="feather-edit-3 me-3"></i>
                                                    <span>Editar</span>
                                                </a>
                                            </li>
                                            @endcan
                                            @can('categoria_despesa.excluir')
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('categorias-despesa.destroy', $categoria) }}" method="POST" data-confirm="Excluir esta categoria?">
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
                        <tr><td colspan="3" class="text-center text-muted py-4">Nenhuma categoria cadastrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
