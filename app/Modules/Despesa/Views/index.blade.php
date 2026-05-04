@extends('layouts.app')

@section('titulo', 'Contas a Pagar - Meu Negócio')
@section('titulo-pagina', 'Contas a Pagar')
@section('breadcrumb')
    <li class="breadcrumb-item active">Contas a Pagar</li>
@endsection

@section('content')
    @can('despesa.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('despesas.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Nova Despesa
            </a>
        </div>
    </div>
    @endcan

    {{-- Filtros --}}
    <div class="card stretch stretch-full mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('despesas.index') }}">
                <div class="row g-3 align-items-end">
                    {{-- Linha 1: Empresa (ME-010 v3) + Busca --}}
                    @include('partials.filtro-empresa-listagem', ['modo' => 'embed', 'colunaCss' => 'col-md-3'])

                    <div class="col-md-9">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="Nome, fornecedor, documento ou ID..." value="{{ request('q') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">
                            Status
                            <x-label-info content="<b>Pendente</b>: nenhuma parcela foi paga ainda.<br><b>Parcial</b>: pelo menos uma parcela foi paga, mas ainda resta saldo.<br><b>Paga</b>: todas as parcelas foram quitadas.<br><b>Vencidas</b>: despesas em aberto com pelo menos uma parcela no passado.<br><b>Cancelada</b>: despesa cancelada (todas as parcelas foram canceladas)." />
                        </label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="pendente" @selected(request('status') === 'pendente')>Pendente</option>
                            <option value="parcial" @selected(request('status') === 'parcial')>Parcial</option>
                            <option value="paga" @selected(request('status') === 'paga')>Paga</option>
                            <option value="vencidas" @selected(request('status') === 'vencidas')>Vencidas</option>
                            <option value="cancelada" @selected(request('status') === 'cancelada')>Cancelada</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Categoria</label>
                        <select name="categoria_id" class="form-select">
                            <option value="">Todas</option>
                            @foreach($categorias as $cat)
                                <option value="{{ $cat->id }}" @selected((int) request('categoria_id') === $cat->id)>{{ $cat->descricao }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">
                            Situação
                            <x-label-info content="Olha o vencimento das parcelas em aberto.<br><b>Em dia</b>: há parcelas a vencer nos próximos dias.<br><b>Vencida</b>: há pelo menos uma parcela com vencimento no passado sem ter sido quitada." />
                        </label>
                        <select name="situacao" class="form-select">
                            <option value="">Todas</option>
                            <option value="em_dia" @selected(request('situacao') === 'em_dia')>Em dia</option>
                            <option value="vencida" @selected(request('situacao') === 'vencida')>Vencida</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mês de referência</label>
                        <input type="month" name="mes_referencia" class="form-control" value="{{ request('mes_referencia') }}">
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('despesas.index') }}" class="btn btn-light" title="Limpar filtros">
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
            <h5 class="card-title">Contas a Pagar</h5>
            <div class="card-header-action">
                <span class="badge bg-light text-dark">{{ $despesas->total() }} registro(s)</span>
            </div>
        </div>
        <div class="card-body custom-card-action">
            @forelse($despesas as $despesa)
                @include('despesa::_despesa_card', ['despesa' => $despesa])
                @if(!$loop->last)
                    <hr class="border-dashed my-3">
                @endif
            @empty
                <div class="text-center text-muted py-5">
                    <i class="feather-trending-down" style="font-size:48px;"></i>
                    <div class="mt-2">Nenhuma conta a pagar encontrada.</div>
                </div>
            @endforelse
        </div>
        @if($despesas->hasPages())
            <div class="card-footer">
                {{ $despesas->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
