@extends('layouts.app')

@section('titulo', 'Despesas - Meu Negócio')
@section('titulo-pagina', 'Despesas')
@section('breadcrumb')
    <li class="breadcrumb-item active">Despesas</li>
@endsection

@section('content')
    {{-- Button row OUTSIDE the card --}}
    @can('despesa.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('despesas.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Nova Despesa
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
                            <th>Valor</th>
                            <th>Data</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($despesas as $despesa)
                        <tr>
                            <td>{{ $despesa->nome }}</td>
                            <td>R$ {{ number_format($despesa->valor, 2, ',', '.') }}</td>
                            <td>{{ \Carbon\Carbon::parse($despesa->data)->format('d/m/Y') }}</td>
                            <td>
                                <div class="hstack gap-2 justify-content-end">
                                    <div class="dropdown">
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            @can('despesa.editar')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('despesas.edit', $despesa) }}">
                                                    <i class="feather-edit-3 me-3"></i>
                                                    <span>Editar</span>
                                                </a>
                                            </li>
                                            @endcan
                                            @can('despesa.excluir')
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('despesas.destroy', $despesa) }}" method="POST" data-confirm="Excluir esta despesa?">
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
                        <tr><td colspan="4" class="text-center text-muted py-4">Nenhuma despesa cadastrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
