@extends('layouts.app')

@section('titulo', 'Registrar Baixa - Meu Negócio')
@section('titulo-pagina', 'Registrar Baixa')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('despesas.index') }}">Contas a Pagar</a></li>
    <li class="breadcrumb-item active">Registrar Baixa</li>
@endsection

@section('content')
    @php
        $saldo = $despesa->saldoRestante();
        $venc = $despesa->data_vencimento;
        $diasAtraso = $venc && $venc->isPast() ? now()->startOfDay()->diffInDays($venc->copy()->startOfDay()) : 0;
    @endphp

    @if($diasAtraso > 0)
    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
        <i class="feather-alert-triangle me-2"></i>
        <div>
            <strong>Despesa em atraso:</strong>
            {{ $diasAtraso }} {{ $diasAtraso === 1 ? 'dia' : 'dias' }}
            (vencimento em {{ $venc->format('d/m/Y') }}).
        </div>
    </div>
    @endif

    {{-- Resumo da despesa --}}
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Despesa #{{ $despesa->id }}</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="fs-12 text-muted">Nome</div>
                    <div class="fw-semibold">{{ $despesa->nome }}</div>
                </div>
                <div class="col-md-4">
                    <div class="fs-12 text-muted">Fornecedor</div>
                    <div class="fw-semibold">{{ $despesa->fornecedor_nome ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="fs-12 text-muted">Categoria</div>
                    <div class="fw-semibold">{{ $despesa->categoria->descricao ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="fs-12 text-muted">Valor total</div>
                    <div class="fw-semibold">R$ {{ number_format($despesa->valor, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="fs-12 text-muted">Valor pago</div>
                    <div class="fw-semibold">R$ {{ number_format($despesa->valor_pago, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="fs-12 text-muted">Saldo restante</div>
                    <div class="fw-semibold fs-18 text-danger">
                        R$ {{ number_format($saldo, 2, ',', '.') }}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="fs-12 text-muted">Vencimento</div>
                    <div class="fw-semibold">{{ $venc?->format('d/m/Y') ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="fs-12 text-muted">Competência</div>
                    <div class="fw-semibold">{{ $despesa->competencia?->format('m/Y') ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="fs-12 text-muted">Documento</div>
                    <div class="fw-semibold">{{ $despesa->documento ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Histórico de baixas anteriores --}}
    @if($despesa->baixas->count() > 0)
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Baixas anteriores</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Forma</th>
                            <th>Observação</th>
                            <th class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($despesa->baixas->sortByDesc('data') as $baixa)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($baixa->data)->format('d/m/Y H:i') }}</td>
                            <td>{{ ucfirst($baixa->forma_pagamento?->value ?? '—') }}</td>
                            <td>{{ $baixa->observacao ?? '—' }}</td>
                            <td class="text-end fw-semibold">R$ {{ number_format($baixa->valor, 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Formulário de baixa --}}
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Registrar nova baixa</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('despesas.baixa', $despesa) }}" method="POST">
                @csrf

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="valor" class="form-label">Valor (R$) <span class="text-danger">*</span></label>
                        <input type="number"
                               step="0.01"
                               min="0.01"
                               max="{{ number_format($saldo, 2, '.', '') }}"
                               name="valor"
                               id="valor"
                               class="form-control @error('valor') is-invalid @enderror"
                               value="{{ old('valor', number_format($saldo, 2, '.', '')) }}"
                               required>
                        <div class="form-text">Máximo: R$ {{ number_format($saldo, 2, ',', '.') }}. Informe valor menor para baixa parcial.</div>
                        @error('valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="forma_pagamento" class="form-label">Forma de Pagamento <span class="text-danger">*</span></label>
                        <select name="forma_pagamento" id="forma_pagamento" class="form-select @error('forma_pagamento') is-invalid @enderror" required>
                            @foreach(\App\Enums\FormaPagamento::cases() as $forma)
                                <option value="{{ $forma->value }}" @selected(old('forma_pagamento') === $forma->value)>{{ ucfirst($forma->value) }}</option>
                            @endforeach
                        </select>
                        @error('forma_pagamento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label for="observacao" class="form-label">Observação</label>
                        <textarea name="observacao" id="observacao" rows="3" class="form-control @error('observacao') is-invalid @enderror">{{ old('observacao') }}</textarea>
                        @error('observacao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="d-flex gap-2 pt-4">
                    <a href="{{ route('despesas.index') }}" class="btn btn-light px-5 py-2" style="min-width: 300px;">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Voltar</span>
                    </a>
                    <button type="submit" class="btn btn-primary px-5 py-2" style="min-width: 300px;">
                        <i class="feather-dollar-sign me-2"></i>
                        <span>Registrar Baixa</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
