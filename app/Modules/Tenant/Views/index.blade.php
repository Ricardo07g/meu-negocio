@extends('layouts.app')

@section('titulo', 'Empresas - Meu Negócio')
@section('titulo-pagina', 'Empresas')
@section('breadcrumb')
    <li class="breadcrumb-item active">Empresas</li>
@endsection

@section('content')
    @include('partials.aviso-limite-plano', [
        'recurso' => 'empresas',
        'atual' => $limite['atual'],
        'maximo' => $limite['maximo'],
        'atingido' => $limite['atingido'],
        'rotaCriar' => route('empresas.create'),
        'labelBotao' => 'Nova Empresa',
        'permissaoBlade' => 'empresa.criar',
    ])

    {{-- Card with table --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Documento</th>
                            <th>Telefone</th>
                            <th>Email</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($empresas as $empresa)
                        <tr>
                            <td>{{ $empresa->nome }}</td>
                            <td>{{ $empresa->documento ?? '-' }}</td>
                            <td>{{ $empresa->telefone ?? '-' }}</td>
                            <td>{{ $empresa->email ?? '-' }}</td>
                            <td>
                                <div class="hstack gap-2 justify-content-end">
                                    <div class="dropdown">
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('empresas.show', $empresa) }}">
                                                    <i class="feather-eye me-3"></i>
                                                    <span>Ver</span>
                                                </a>
                                            </li>
                                            @can('empresa.editar')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('empresas.edit', $empresa) }}">
                                                    <i class="feather-edit-3 me-3"></i>
                                                    <span>Editar</span>
                                                </a>
                                            </li>
                                            @endcan
                                            @can('empresa.excluir')
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('empresas.destroy', $empresa) }}" method="POST" data-confirm="Excluir esta empresa?">
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
                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma empresa cadastrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
