@extends('layouts.app')

@section('titulo', 'Contas a Receber - Meu Negócio')
@section('titulo-pagina', 'Contas a Receber')
@section('breadcrumb')
    <li class="breadcrumb-item active">Contas a Receber</li>
@endsection

@section('content')
    {{-- Filtros --}}
    <div class="card stretch stretch-full mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('pagamentos.index') }}">
                <div class="row g-3 align-items-end">
                    {{-- Linha 1: Empresa (ME-010 v3) + Busca --}}
                    @include('partials.filtro-empresa-listagem', ['modo' => 'embed', 'colunaCss' => 'col-md-3'])

                    <div class="col-md-9">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="Cliente ou ID do pagamento..." value="{{ request('q') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">
                            Status
                            <x-label-info content="<b>Pendente</b>: nenhuma parcela foi recebida ainda.<br><b>Parcial</b>: pelo menos uma parcela foi recebida, mas ainda há saldo.<br><b>Pago</b>: todas as parcelas foram recebidas.<br><b>Estornado</b>: venda de origem foi cancelada.<br><b>Cancelado</b>: todas as parcelas foram canceladas." />
                        </label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="pendente" @selected(request('status') === 'pendente')>Pendente</option>
                            <option value="parcial" @selected(request('status') === 'parcial')>Parcial</option>
                            <option value="pago" @selected(request('status') === 'pago')>Pago</option>
                            <option value="estornado" @selected(request('status') === 'estornado')>Estornado</option>
                            <option value="cancelado" @selected(request('status') === 'cancelado')>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Origem</label>
                        <select name="origem" class="form-select">
                            <option value="">Todas</option>
                            <option value="avulso" @selected(request('origem') === 'avulso')>Agendamento avulso</option>
                            <option value="pacote" @selected(request('origem') === 'pacote')>Pacote de sessões</option>
                            <option value="produto" @selected(request('origem') === 'produto')>Venda de produto</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">
                            Situação
                            <x-label-info content="Olha o vencimento das parcelas em aberto.<br><b>Em dia</b>: há parcelas a vencer nos próximos dias.<br><b>Vencido</b>: há pelo menos uma parcela com vencimento no passado sem ter sido quitada." />
                        </label>
                        <select name="situacao" class="form-select">
                            <option value="">Todas</option>
                            <option value="em_dia" @selected(request('situacao') === 'em_dia')>Em dia</option>
                            <option value="vencido" @selected(request('situacao') === 'vencido')>Vencido</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mês de referência</label>
                        <input type="month" name="mes_referencia" class="form-control" value="{{ request('mes_referencia') }}">
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
