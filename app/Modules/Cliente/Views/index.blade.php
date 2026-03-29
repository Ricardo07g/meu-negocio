@extends('layouts.app')

@section('titulo', 'Clientes - Meu Negocio')
@section('titulo-pagina', 'Clientes')
@section('breadcrumb')
    <li class="breadcrumb-item active">Clientes</li>
@endsection

@section('content')
    {{-- Botao Novo Cliente --}}
    @can('cliente.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('clientes.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Novo Cliente
            </a>
        </div>
    </div>
    @endcan

    {{-- Tabela --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>Email</th>
                            <th>Cidade</th>
                            <th class="text-end">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clientes as $cliente)
                        <tr>
                            <td>{{ $cliente->nome }}</td>
                            <td>
                                {{ $cliente->telefone ?? '-' }}
                                @if($cliente->telefone_whatsapp)
                                    <i class="feather-message-circle text-success ms-1" title="WhatsApp"></i>
                                @endif
                            </td>
                            <td>{{ $cliente->email ?? '-' }}</td>
                            <td>{{ $cliente->cidade ? $cliente->cidade . ($cliente->estado ? '/' . $cliente->estado : '') : '-' }}</td>
                            <td>
                                <div class="hstack gap-2 justify-content-end">
                                    <div class="dropdown">
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('clientes.show', $cliente) }}">
                                                    <i class="feather-eye me-3"></i>
                                                    <span>Ver</span>
                                                </a>
                                            </li>
                                            @can('cliente.editar')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('clientes.edit', $cliente) }}">
                                                    <i class="feather-edit-3 me-3"></i>
                                                    <span>Editar</span>
                                                </a>
                                            </li>
                                            @endcan
                                            @can('cliente.excluir')
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('clientes.destroy', $cliente) }}" method="POST" data-confirm="Excluir este cliente?">
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
                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhum cliente cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
