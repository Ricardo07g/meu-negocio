@extends('layouts.app')

@section('titulo', 'Detalhes da venda - Meu Negócio')
@section('titulo-pagina', 'Venda #' . str_pad($venda->id, 6, '0', STR_PAD_LEFT))
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('vendas.index') }}">Vendas</a></li>
    <li class="breadcrumb-item active">Detalhes</li>
@endsection

@section('content')
    @php
        $tipoLabel = match ($venda->tipo) {
            'produto' => 'Produto',
            'etapas' => 'Serviço em etapas',
            default => 'Serviço único',
        };
        $tipoBadge = $venda->tipo === 'produto'
            ? 'bg-soft-warning text-warning'
            : 'bg-soft-info text-info';
        $vendaCancelada = in_array($venda->status, ['cancelado', 'cancelada']);
    @endphp

    <div class="row justify-content-center">
        <div class="col-xxl-9 col-xl-11">
            @if($vendaCancelada)
                <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                    <i class="feather-x-circle"></i>
                    <span>Esta venda foi cancelada. Os lançamentos (estoque/pagamento) foram desfeitos.</span>
                </div>
            @endif

            <div class="card stretch stretch-full mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                        <div>
                            <h5 class="fw-bold mb-1">{{ $venda->cliente }}</h5>
                            <div class="text-muted">{{ $venda->servico }}</div>
                            <div class="fs-20 fw-bold mt-2">R$ {{ number_format($venda->valor, 2, ',', '.') }}</div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-2">
                            <x-badge-status :cor="$venda->cor" :label="$venda->status_label" />
                            <span class="badge {{ $tipoBadge }}">{{ $tipoLabel }}</span>
                        </div>
                    </div>
                </div>
            </div>

            @include('venda::_venda_detalhe', ['venda' => $venda])

            <div class="mt-4">
                <x-show-botoes :voltar="route('vendas.index')" />
            </div>
        </div>
    </div>
@endsection
