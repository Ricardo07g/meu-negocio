@extends('layouts.app')

@section('titulo', 'Vendas - Meu Negócio')
@section('titulo-pagina', 'Vendas')
@section('breadcrumb')
    <li class="breadcrumb-item active">Vendas</li>
@endsection

@section('content')
    @can('agendamento.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('vendas.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Nova Venda
            </a>
        </div>
    </div>
    @endcan

    {{-- Filtros --}}
    <x-filtros-listagem :action="route('vendas.index')"
        :ativo="collect(request()->except('page'))->filter(fn ($v) => filled($v))->isNotEmpty()">
        {{-- Linha 1: Empresa (ME-010 v3) + Busca --}}
        @include('partials.filtro-empresa-listagem', ['modo' => 'embed', 'colunaCss' => 'col-12 col-sm-6 col-md-3'])

        <div class="col-12 col-md-9">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control" placeholder="Cliente, serviço/produto ou ID da venda..." value="{{ request('q') }}">
        </div>

        {{-- Linha 2: Periodo + Tipo + Pagto + Status --}}
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">Período</label>
            <select name="periodo_preset" class="form-select">
                <option value="">Todos</option>
                <option value="hoje" @selected(request('periodo_preset') === 'hoje')>Hoje</option>
                <option value="ontem" @selected(request('periodo_preset') === 'ontem')>Ontem</option>
                <option value="esta_semana" @selected(request('periodo_preset') === 'esta_semana')>Esta semana</option>
                <option value="este_mes" @selected(request('periodo_preset') === 'este_mes')>Este mês</option>
                <option value="mes_passado" @selected(request('periodo_preset') === 'mes_passado')>Mês passado</option>
                <option value="ultimos_30_dias" @selected(request('periodo_preset') === 'ultimos_30_dias')>Últimos 30 dias</option>
                <option value="ultimos_90_dias" @selected(request('periodo_preset') === 'ultimos_90_dias')>Últimos 90 dias</option>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select">
                <option value="">Todos</option>
                <option value="servico" @selected(request('tipo') === 'servico')>Serviço</option>
                <option value="produto" @selected(request('tipo') === 'produto')>Produto</option>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">
                Situação do pagamento
                <x-label-info content="<b>Pago</b>: todas as parcelas foram recebidas.<br><b>Pendente</b>: há parcelas em aberto com vencimento futuro.<br><b>Vencido</b>: há parcelas em aberto com vencimento no passado.<br><b>Estornado</b>: venda foi cancelada e o pagamento foi revertido." />
            </label>
            <select name="situacao_pagamento" class="form-select">
                <option value="">Todas</option>
                <option value="pago" @selected(request('situacao_pagamento') === 'pago')>Pago</option>
                <option value="pendente" @selected(request('situacao_pagamento') === 'pendente')>Pendente</option>
                <option value="vencido" @selected(request('situacao_pagamento') === 'vencido')>Vencido</option>
                <option value="estornado" @selected(request('situacao_pagamento') === 'estornado')>Estornado</option>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">
                Status da venda
                <x-label-info content="<b>Em andamento</b>: venda ativa (agendada, confirmada ou com parcelas em aberto).<br><b>Concluído</b>: venda finalizada ou com todas as sessões realizadas.<br><b>Cancelado</b>: venda cancelada — estoque devolvido e pagamento estornado." />
            </label>
            <select name="status_venda" class="form-select">
                <option value="">Todos</option>
                <option value="em_andamento" @selected(request('status_venda') === 'em_andamento')>Em andamento</option>
                <option value="concluido" @selected(request('status_venda') === 'concluido')>Concluído</option>
                <option value="cancelado" @selected(request('status_venda') === 'cancelado')>Cancelado</option>
            </select>
        </div>

        {{-- Linha 3: Data custom + Forma pgto + Atendente --}}
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">Data início</label>
            <input type="date" name="data_inicio" class="form-control" value="{{ request('data_inicio') }}">
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">Data fim</label>
            <input type="date" name="data_fim" class="form-control" value="{{ request('data_fim') }}">
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">Forma de pagamento</label>
            <select name="forma_pagamento" class="form-select">
                <option value="">Todas</option>
                @foreach(($formas ?? []) as $forma)
                    <option value="{{ $forma->id }}" @selected(request('forma_pagamento') === (string) $forma->id)>{{ $forma->nome }}</option>
                @endforeach
                <option value="a_vista" @selected(request('forma_pagamento') === 'a_vista')>À Vista</option>
                <option value="a_prazo" @selected(request('forma_pagamento') === 'a_prazo')>A Prazo</option>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">Atendente/Vendedor</label>
            <select name="atendente_id" class="form-select">
                <option value="">Todos</option>
                @foreach($atendentes as $at)
                    <option value="{{ $at->id }}" @selected((int) request('atendente_id') === $at->id)>{{ $at->nome }}</option>
                @endforeach
            </select>
        </div>

        {{-- Linha 4: Valor min/max --}}
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">Valor mínimo (R$)</label>
            <input type="number" name="valor_min" class="form-control" step="0.01" min="0" value="{{ request('valor_min') }}">
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label">Valor máximo (R$)</label>
            <input type="number" name="valor_max" class="form-control" step="0.01" min="0" value="{{ request('valor_max') }}">
        </div>
    </x-filtros-listagem>

    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Todas as Vendas</h5>
            <div class="card-header-action">
                <span class="badge bg-light text-dark">{{ $vendas->total() }} registro(s)</span>
            </div>
        </div>
        <div class="card-body custom-card-action">
            @forelse($vendas as $venda)
                @include('venda::_venda_card', ['venda' => $venda])
                @if(!$loop->last)
                    <hr class="border-dashed my-3">
                @endif
            @empty
                <div class="text-center text-muted py-5">
                    <i class="feather-shopping-bag" style="font-size:48px;"></i>
                    <div class="mt-2">Nenhuma venda registrada.</div>
                </div>
            @endforelse
        </div>
        @if($vendas->hasPages())
            <div class="card-footer">
                {{ $vendas->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
