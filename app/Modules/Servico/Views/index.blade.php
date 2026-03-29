@extends('layouts.app')

@section('titulo', 'Serviços - Meu Negócio')
@section('titulo-pagina', 'Serviços')
@section('breadcrumb')
    <li class="breadcrumb-item active">Serviços</li>
@endsection

@section('content')
    {{-- Button row OUTSIDE the card --}}
    @can('servico.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('servicos.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Novo Serviço
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
                            <th>Tipo</th>
                            <th>Duração (min)</th>
                            <th>Valor</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($servicos as $servico)
                        <tr>
                            <td>{{ $servico->nome }}</td>
                            <td>
                                @switch($servico->tipo->value)
                                    @case('pacote')
                                        <span class="badge bg-primary">Pacote ({{ $servico->qtd_sessoes }}x)</span>
                                        @break
                                    @default
                                        <span class="badge bg-light text-dark">Avulso</span>
                                @endswitch
                            </td>
                            <td>{{ $servico->duracao }} min</td>
                            <td>R$ {{ number_format($servico->valor, 2, ',', '.') }}</td>
                            <td>
                                <div class="hstack gap-2 justify-content-end">
                                    <div class="dropdown">
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('servicos.show', $servico) }}">
                                                    <i class="feather-eye me-3"></i>
                                                    <span>Ver</span>
                                                </a>
                                            </li>
                                            @can('servico.editar')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('servicos.edit', $servico) }}">
                                                    <i class="feather-edit-3 me-3"></i>
                                                    <span>Editar</span>
                                                </a>
                                            </li>
                                            @endcan
                                            @can('servico.excluir')
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('servicos.destroy', $servico) }}" method="POST" data-confirm="Excluir este serviço?">
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
                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhum serviço cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
