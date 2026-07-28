@extends('layouts.app')

@section('titulo', 'Empresas - Meu Negócio')
@section('titulo-pagina', 'Empresas')
@section('breadcrumb')
    <li class="breadcrumb-item active">Empresas</li>
@endsection

@section('content')
    {{-- Cada unidade e uma licenca contratada. Contratar outra e ato comercial: nao ha
         botao de "Nova Empresa" — o operador do SaaS provisiona. --}}
    <div class="alert alert-light border d-flex align-items-center mb-3" role="alert">
        <i class="feather-info me-2"></i>
        <div class="flex-grow-1">
            <strong>{{ $empresas->count() }}</strong>
            {{ $empresas->count() === 1 ? 'unidade licenciada' : 'unidades licenciadas' }}.
            Para contratar outra unidade, fale com o suporte.
        </div>
    </div>

    {{-- Card with table --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Licença</th>
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
                            <td>
                                <span class="badge bg-soft-{{ $empresa->plano->tem_financeiro ? 'primary text-primary' : 'secondary text-secondary' }}">
                                    {{ $empresa->plano->nome }}
                                </span>
                                @if ($empresa->emTrial())
                                    <small class="text-muted ms-1">teste · {{ $empresa->diasRestantesTrial() }}d</small>
                                @endif
                            </td>
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
                                            @can('empresa.editar')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('empresas.edit', $empresa) }}">
                                                    <i class="feather-edit-3 me-3"></i>
                                                    <span>Editar</span>
                                                </a>
                                            </li>
                                            @endcan
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma unidade licenciada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
