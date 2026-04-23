@extends('layouts.app')

@section('titulo', 'Pagar Parcela - Meu Negócio')
@section('titulo-pagina', 'Pagar Parcela')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('despesas.index') }}">Contas a Pagar</a></li>
    <li class="breadcrumb-item active">Pagar Parcela</li>
@endsection

@section('content')
    @php
        $despesa = $parcela->despesa;
        $saldo = $parcela->saldoRestante();
        $diasAtraso = $parcela->diasAtraso();
    @endphp

    @if($diasAtraso > 0)
    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
        <i class="feather-alert-triangle me-2"></i>
        <div>
            <strong>Parcela em atraso:</strong>
            {{ $diasAtraso }} {{ $diasAtraso === 1 ? 'dia' : 'dias' }}
            (vencimento em {{ $parcela->data_vencimento->format('d/m/Y') }}).
        </div>
    </div>
    @endif

    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Parcela {{ $parcela->numero }}/{{ $parcela->total }} — Despesa #{{ $despesa->id }}</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="fs-12 text-muted">Despesa</div>
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
                <div class="col-md-3">
                    <div class="fs-12 text-muted">Valor da parcela</div>
                    <div class="fw-semibold">R$ {{ number_format($parcela->valor, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="fs-12 text-muted">Já pago (líquido)</div>
                    <div class="fw-semibold">R$ {{ number_format($parcela->valorPagoLiquido(), 2, ',', '.') }}</div>
                    @if(abs($parcela->valorPagoLiquido() - (float) $parcela->valor_pago) > 0.009)
                        <div class="fs-11 text-muted">Principal quitado: R$ {{ number_format($parcela->valor_pago, 2, ',', '.') }}</div>
                    @endif
                </div>
                <div class="col-md-3">
                    <div class="fs-12 text-muted">Saldo restante</div>
                    <div class="fw-semibold fs-18 text-danger">R$ {{ number_format($saldo, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="fs-12 text-muted">Vencimento</div>
                    <div class="fw-semibold">{{ $parcela->data_vencimento->format('d/m/Y') }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($parcela->baixas->count() > 0)
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Pagamentos anteriores desta parcela</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Forma</th>
                            <th>Observação</th>
                            <th class="text-end">Principal</th>
                            <th class="text-end">Desconto</th>
                            <th class="text-end">Multa</th>
                            <th class="text-end">Juros</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($parcela->baixas->sortByDesc('data') as $baixa)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($baixa->data)->format('d/m/Y H:i') }}</td>
                            <td>{{ $baixa->forma_pagamento?->label() ?? '—' }}</td>
                            <td>{{ $baixa->observacao ?? '—' }}</td>
                            <td class="text-end">R$ {{ number_format($baixa->valor, 2, ',', '.') }}</td>
                            <td class="text-end {{ $baixa->desconto > 0 ? 'text-success' : 'text-muted' }}">
                                {{ $baixa->desconto > 0 ? '-R$ ' . number_format($baixa->desconto, 2, ',', '.') : '—' }}
                            </td>
                            <td class="text-end">R$ {{ number_format($baixa->multa, 2, ',', '.') }}</td>
                            <td class="text-end">R$ {{ number_format($baixa->juros, 2, ',', '.') }}</td>
                            <td class="text-end fw-semibold">R$ {{ number_format($baixa->valorTotal(), 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Registrar pagamento</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('parcelas-despesa.baixa', $parcela) }}" method="POST">
                @csrf

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="valor" class="form-label">Valor principal (R$) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01"
                               max="{{ number_format($saldo, 2, '.', '') }}"
                               name="valor" id="valor"
                               class="form-control @error('valor') is-invalid @enderror"
                               value="{{ old('valor', number_format($saldo, 2, '.', '')) }}" required>
                        <div class="form-text">Máximo: R$ {{ number_format($saldo, 2, ',', '.') }}.</div>
                        @error('valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="desconto" class="form-label">Desconto (R$)</label>
                        <input type="number" step="0.01" min="0" name="desconto" id="desconto"
                               class="form-control @error('desconto') is-invalid @enderror"
                               value="{{ old('desconto', '0.00') }}">
                        <div class="form-text">Abatimento do fornecedor.</div>
                        @error('desconto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="multa" class="form-label">Multa (R$)</label>
                        <input type="number" step="0.01" min="0" name="multa" id="multa"
                               class="form-control @error('multa') is-invalid @enderror"
                               value="{{ old('multa', '0.00') }}">
                        @error('multa') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="juros" class="form-label">Juros (R$)</label>
                        <input type="number" step="0.01" min="0" name="juros" id="juros"
                               class="form-control @error('juros') is-invalid @enderror"
                               value="{{ old('juros', '0.00') }}">
                        @error('juros') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-8">
                        <label for="forma_pagamento" class="form-label">Forma de Pagamento <span class="text-danger">*</span></label>
                        <select name="forma_pagamento" id="forma_pagamento" class="form-select @error('forma_pagamento') is-invalid @enderror" required>
                            @foreach(\App\Enums\FormaPagamento::cases() as $forma)
                                <option value="{{ $forma->value }}" @selected(old('forma_pagamento') === $forma->value)>{{ $forma->label() }}</option>
                            @endforeach
                        </select>
                        @error('forma_pagamento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <div class="alert alert-light border d-flex justify-content-between align-items-center mb-0">
                            <span class="fw-semibold">Total a pagar</span>
                            <span class="fs-18 fw-bold text-danger" id="totalPago">R$ 0,00</span>
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="observacao" class="form-label">Observação</label>
                        <textarea name="observacao" id="observacao" rows="3" class="form-control @error('observacao') is-invalid @enderror">{{ old('observacao') }}</textarea>
                        @error('observacao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="d-flex gap-2 pt-4">
                    <a href="{{ route('despesas.index') }}" class="btn btn-light px-5 py-2" style="min-width: 300px;">
                        <i class="feather-arrow-left me-2"></i>Voltar
                    </a>
                    <button type="submit" class="btn btn-primary px-5 py-2" style="min-width: 300px;">
                        <i class="feather-dollar-sign me-2"></i>Registrar Pagamento
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const valor = document.getElementById('valor');
    const multa = document.getElementById('multa');
    const juros = document.getElementById('juros');
    const desconto = document.getElementById('desconto');
    const total = document.getElementById('totalPago');

    function atualizarTotal() {
        const v = parseFloat(valor.value) || 0;
        const m = parseFloat(multa.value) || 0;
        const j = parseFloat(juros.value) || 0;
        const d = parseFloat(desconto.value) || 0;
        total.textContent = 'R$ ' + (v + m + j - d).toFixed(2).replace('.', ',');
    }

    [valor, multa, juros, desconto].forEach(el => el.addEventListener('input', atualizarTotal));
    atualizarTotal();
});
</script>
@endpush
