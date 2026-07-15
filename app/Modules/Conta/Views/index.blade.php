@extends('layouts.app')

@section('titulo', 'Contas - Meu Negócio')
@section('titulo-pagina', 'Contas')
@section('breadcrumb')
    <li class="breadcrumb-item active">Contas</li>
@endsection

@section('content')
    @php $multiEmpresa = count((array) session('empresas_atuais', [])) > 1; @endphp

    @can('conta.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('contas.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Nova Conta
            </a>
        </div>
    </div>
    @endcan

    <div class="card stretch stretch-full mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('contas.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="Nome da conta..." value="{{ request('q') }}">
                    </div>
                    @include('partials.filtro-empresa-listagem', ['modo' => 'embed', 'colunaCss' => 'col-12 col-sm-6 col-md-3'])
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos</option>
                            @foreach($tipos as $tipo)
                                <option value="{{ $tipo->value }}" @selected(request('tipo') === $tipo->value)>{{ $tipo->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="ativo" class="form-select">
                            <option value="">Todos</option>
                            <option value="1" @selected(request('ativo') === '1')>Ativa</option>
                            <option value="0" @selected(request('ativo') === '0')>Inativa</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('contas.index') }}" class="btn btn-light"><i class="feather-x me-1"></i>Limpar</a>
                        <button type="submit" class="btn btn-primary"><i class="feather-filter me-1"></i>Filtrar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            @if($multiEmpresa)<th>Empresa</th>@endif
                            <th>Tipo</th>
                            <th>Padrão</th>
                            <th class="text-end">Saldo atual</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contas as $conta)
                        <tr>
                            <td><i class="{{ $conta->tipo->icone() }} me-2 text-muted"></i>{{ $conta->nome }}</td>
                            @if($multiEmpresa)<td class="text-muted">{{ $conta->empresa?->nome ?? '—' }}</td>@endif
                            <td><span class="badge bg-soft-secondary text-secondary">{{ $conta->tipo->label() }}</span></td>
                            <td>
                                @if($conta->eh_caixa_padrao)
                                    <span class="badge bg-soft-success text-success">Caixa</span>
                                @endif
                                @if($conta->eh_destino_recebivel_padrao)
                                    <span class="badge bg-soft-info text-info">Recebíveis</span>
                                @endif
                            </td>
                            <td class="text-end">R$ {{ number_format($conta->saldo(), 2, ',', '.') }}</td>
                            <td>
                                @if($conta->ativo)
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
                                            @can('conta.editar')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('contas.edit', $conta) }}">
                                                    <i class="feather-edit-3 me-3"></i><span>Editar</span>
                                                </a>
                                            </li>
                                            @endcan
                                            @can('conta.excluir')
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('contas.destroy', $conta) }}" method="POST" data-confirm="Excluir esta conta?">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="feather-trash-2 me-3"></i><span>Excluir</span>
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
                        <tr><td colspan="{{ $multiEmpresa ? 7 : 6 }}" class="text-center text-muted py-4">Nenhuma conta cadastrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
