@extends('layouts.app')

@section('titulo', 'Contas a Receber - Meu Negócio')
@section('titulo-pagina', 'Contas a Receber')
@section('breadcrumb')
    <li class="breadcrumb-item active">Contas a Receber</li>
@endsection

@section('content')
    @can('pagamento.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('pagamentos.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Novo Recebimento
            </a>
        </div>
    </div>
    @endcan

    {{-- Filtros --}}
    <div class="card stretch stretch-full mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('pagamentos.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-12">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="Cliente ou ID do pagamento..." value="{{ request('q') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="pendente" @selected(request('status') === 'pendente')>Pendente</option>
                            <option value="pago" @selected(request('status') === 'pago')>Pago</option>
                            <option value="estornado" @selected(request('status') === 'estornado')>Estornado</option>
                            <option value="cancelado" @selected(request('status') === 'cancelado')>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Origem</label>
                        <select name="origem" class="form-select">
                            <option value="">Todas</option>
                            <option value="avulso" @selected(request('origem') === 'avulso')>Agendamento avulso</option>
                            <option value="pacote" @selected(request('origem') === 'pacote')>Pacote de sessões</option>
                            <option value="produto" @selected(request('origem') === 'produto')>Venda de produto</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Situação</label>
                        <select name="situacao" class="form-select">
                            <option value="">Todas</option>
                            <option value="em_dia" @selected(request('situacao') === 'em_dia')>Em dia</option>
                            <option value="vencido" @selected(request('situacao') === 'vencido')>Vencido</option>
                        </select>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('pagamentos.index') }}" class="btn btn-light" title="Limpar filtros">
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

    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Contas a Receber</h5>
            <div class="card-header-action">
                <span class="badge bg-light text-dark">{{ $pagamentos->total() }} registro(s)</span>
            </div>
        </div>
        <div class="card-body custom-card-action">
            @forelse($pagamentos as $pagamento)
                @include('pagamento::_pagamento_card', ['pagamento' => $pagamento])
                @if(!$loop->last)
                    <hr class="border-dashed my-3">
                @endif
            @empty
                <div class="text-center text-muted py-5">
                    <i class="feather-dollar-sign" style="font-size:48px;"></i>
                    <div class="mt-2">Nenhuma conta a receber encontrada.</div>
                </div>
            @endforelse
        </div>
        @if($pagamentos->hasPages())
            <div class="card-footer">
                {{ $pagamentos->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
