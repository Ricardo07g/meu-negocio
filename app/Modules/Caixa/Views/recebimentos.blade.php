@extends('layouts.app')

@section('titulo', 'Recebimentos por período - Meu Negócio')
@section('titulo-pagina', 'Recebimentos')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('caixas.index') }}">Caixa</a></li>
    <li class="breadcrumb-item active">Recebimentos</li>
@endsection

@section('content')
    {{-- Filtro de período --}}
    <div class="card stretch stretch-full mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('caixas.recebimentos') }}" class="row g-3 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label" for="de">De</label>
                    <input type="date" name="de" id="de" class="form-control" value="{{ $de }}" max="{{ $ate }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="ate">Até</label>
                    <input type="date" name="ate" id="ate" class="form-control" value="{{ $ate }}">
                </div>
                <div class="col-12 col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="feather-search me-1"></i>Filtrar</button>
                    <a href="{{ route('caixas.index') }}" class="btn btn-light"><i class="feather-arrow-left me-1"></i>Voltar ao caixa</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Resumo por forma (mesmo eixo do painel diário, agora por período) --}}
    <div class="card stretch stretch-full mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Recebimentos por forma</h5>
            <span class="badge bg-soft-primary text-primary">Líquido: R$ {{ number_format($resumo['liquido'], 2, ',', '.') }}</span>
        </div>
        <div class="card-body">
            <p class="text-muted fs-12 mb-3">
                <i class="feather-info me-1"></i>Tudo que o cliente pagou no período, por forma (pela data do pagamento — não pela liquidação no banco). Dinheiro entra na gaveta do caixa; as demais formas são registradas aqui.
            </p>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Forma</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-end">Recebido</th>
                            <th class="text-end">Estornado</th>
                            <th class="text-end">Líquido</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($resumo['linhas'] as $linha)
                        <tr>
                            <td>{{ $linha['forma'] }}</td>
                            <td class="text-center text-muted">{{ $linha['qtd'] }}</td>
                            <td class="text-end text-success">R$ {{ number_format($linha['recebido'], 2, ',', '.') }}</td>
                            <td class="text-end {{ $linha['estornado'] > 0 ? 'text-danger' : 'text-muted' }}">
                                @if($linha['estornado'] > 0)− R$ {{ number_format($linha['estornado'], 2, ',', '.') }}@else—@endif
                            </td>
                            <td class="text-end fw-semibold">R$ {{ number_format($linha['liquido'], 2, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhum recebimento no período.</td></tr>
                        @endforelse
                    </tbody>
                    @if(count($resumo['linhas']) > 0)
                    <tfoot>
                        <tr class="fw-bold border-top">
                            <td>Total</td>
                            <td></td>
                            <td class="text-end text-success">R$ {{ number_format($resumo['totalRecebido'], 2, ',', '.') }}</td>
                            <td class="text-end {{ $resumo['totalEstornado'] > 0 ? 'text-danger' : 'text-muted' }}">
                                @if($resumo['totalEstornado'] > 0)− R$ {{ number_format($resumo['totalEstornado'], 2, ',', '.') }}@else—@endif
                            </td>
                            <td class="text-end">R$ {{ number_format($resumo['liquido'], 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- Detalhe: um recebimento (baixa) por linha --}}
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title mb-0">Detalhe dos recebimentos</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Forma</th>
                            <th>Cliente</th>
                            <th class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recebimentos as $baixa)
                        <tr class="{{ $baixa->estornado_em ? 'text-muted' : '' }}">
                            <td>{{ \Carbon\Carbon::parse($baixa->data)->format('d/m/Y H:i') }}</td>
                            <td>
                                {{ $baixa->forma_pagamento_nome ?? '—' }}
                                @if($baixa->estornado_em)<span class="badge bg-soft-secondary text-secondary ms-1">Estornado</span>@endif
                            </td>
                            <td>{{ $baixa->parcela?->pagamento?->cliente?->nome ?? '—' }}</td>
                            <td class="text-end {{ $baixa->estornado_em ? 'text-decoration-line-through' : 'fw-semibold' }}">R$ {{ number_format((float) $baixa->valorTotal(), 2, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Nenhum recebimento no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
