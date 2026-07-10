@extends('layouts.app')

@section('titulo', 'Editar Serviço em Etapas - Meu Negócio')
@section('titulo-pagina', 'Editar Serviço em Etapas')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('vendas.index') }}">Vendas</a></li>
    <li class="breadcrumb-item active">Editar serviço em etapas</li>
@endsection

@section('content')
    @php
        $subtotalEtapas = (float) $etapas->valor_total + (float) $etapas->desconto - (float) $etapas->acrescimo;
    @endphp

    <form action="{{ route('vendas.update-etapas', $etapas) }}" method="POST">
        @csrf @method('PATCH')

        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Venda em Etapas #{{ $etapas->id }} — {{ $etapas->servico->nome ?? '' }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente <span class="text-danger">*</span></label>
                        <input type="text" id="clienteSearch" class="form-control @error('cliente_id') is-invalid @enderror" placeholder="Digite o nome ou telefone..." autocomplete="off" value="{{ old('_cliente_nome', $etapas->cliente->nome ?? '') }}">
                        <input type="hidden" name="cliente_id" id="clienteHidden" value="{{ old('cliente_id', $etapas->cliente_id) }}">
                        @error('cliente_id') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Etapas</label>
                        <input type="text" class="form-control" value="{{ $etapas->qtd_etapas }}" disabled>
                        <div class="fs-12 text-muted mt-1">A quantidade de etapas não pode ser alterada após a venda.</div>
                    </div>

                    <div class="col-12 col-sm-4">
                        <label class="form-label">Subtotal</label>
                        <input type="text" class="form-control" value="R$ {{ number_format($subtotalEtapas, 2, ',', '.') }}" disabled>
                    </div>
                    <div class="col-6 col-sm-4">
                        <label class="form-label">Desconto (R$)</label>
                        <input type="number" step="0.01" min="0" name="desconto" id="desconto" class="form-control @error('desconto') is-invalid @enderror" value="{{ old('desconto', number_format((float) $etapas->desconto, 2, '.', '')) }}">
                        @error('desconto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6 col-sm-4">
                        <label class="form-label">Acréscimo (R$)</label>
                        <input type="number" step="0.01" min="0" name="acrescimo" id="acrescimo" class="form-control @error('acrescimo') is-invalid @enderror" value="{{ old('acrescimo', number_format((float) $etapas->acrescimo, 2, '.', '')) }}">
                        @error('acrescimo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <div class="alert alert-info fs-13 mb-0">
                            <strong>Total atualizado: <span id="totalCalc">R$ {{ number_format((float) $etapas->valor_total, 2, ',', '.') }}</span></strong>
                            <span class="text-muted ms-2">(subtotal − desconto + acréscimo)</span>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Observações</label>
                        <textarea name="observacao" rows="3" class="form-control @error('observacao') is-invalid @enderror">{{ old('observacao', $etapas->observacao) }}</textarea>
                        @error('observacao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <x-form-botoes :voltar="route('vendas.index')" />
    </form>
@endsection

@push('js')
<script>
    initAjaxSearch({
        inputId: 'clienteSearch',
        hiddenId: 'clienteHidden',
        url: '{{ route("clientes.buscar") }}',
        renderItem: function(item) {
            return '<strong>' + item.nome + '</strong>' + (item.telefone ? '<br><small class="text-muted">' + item.telefone + '</small>' : '');
        },
        displayText: function(item) { return item.nome; },
    });

    const subtotal = {{ $subtotalEtapas }};
    const inputDesc = document.getElementById('desconto');
    const inputAcr = document.getElementById('acrescimo');
    const spanTotal = document.getElementById('totalCalc');

    function recalcular() {
        const d = parseFloat(inputDesc.value || 0);
        const a = parseFloat(inputAcr.value || 0);
        const total = subtotal - d + a;
        spanTotal.textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
    }
    inputDesc.addEventListener('input', recalcular);
    inputAcr.addEventListener('input', recalcular);
</script>
@endpush
