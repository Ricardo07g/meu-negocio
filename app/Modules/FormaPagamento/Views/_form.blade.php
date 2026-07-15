@php
    $entidade = $entidade ?? null;
    // Faixas para repopular: erro de validação (old) tem prioridade sobre o persistido.
    $faixas = old('taxas', $entidade?->taxas?->map(fn ($t) => [
        'parcela_min' => $t->parcela_min,
        'parcela_max' => $t->parcela_max,
        'taxa_percentual' => $t->taxa_percentual,
    ])->all() ?? []);
@endphp

<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">{{ isset($entidade) ? 'Editar' : 'Cadastrar' }} Forma de Pagamento</h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <label class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $entidade?->nome) }}" maxlength="100" placeholder="Ex.: Crédito Cielo" required>
                @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Tipo <span class="text-danger">*</span></label>
                <select name="tipo" id="fp-tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                    @foreach($tipos as $tipo)
                        <option value="{{ $tipo->value }}"
                            data-gera-recebivel="{{ $tipo->geraRecebivelPadrao() ? 1 : 0 }}"
                            data-dias="{{ $tipo->diasLiquidacaoPadrao() }}"
                            data-permite-parcelas="{{ $tipo->permiteParcelasPadrao() ? 1 : 0 }}"
                            @selected(old('tipo', $entidade?->tipo?->value) === $tipo->value)>{{ $tipo->label() }}</option>
                    @endforeach
                </select>
                @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2 d-flex align-items-center pt-4">
                <div class="form-check">
                    <input type="hidden" name="ativo" value="0">
                    <input type="checkbox" name="ativo" value="1" class="form-check-input" id="ativo" {{ old('ativo', $entidade?->ativo ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="ativo">Ativa</label>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4 d-flex align-items-center pt-2">
                <div class="form-check">
                    <input type="hidden" name="gera_recebivel" value="0">
                    <input type="checkbox" name="gera_recebivel" value="1" class="form-check-input" id="fp-gera-recebivel" {{ old('gera_recebivel', $entidade?->gera_recebivel ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="fp-gera-recebivel">
                        Gera recebível
                        <x-label-info content="Quando marcado, o dinheiro <b>não entra na gaveta do caixa</b>: vira um recebível do banco/adquirente, líquido de taxa, previsto para D+N. Típico de cartão. Desmarcado, entra no caixa na hora (dinheiro/pix)." />
                    </label>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    Dias para liquidação (D+N)
                    <x-label-info content="Dias até o dinheiro cair (o banco pagar). 0 = imediato. Débito ≈ 1, crédito ≈ 30." />
                </label>
                <input type="number" name="dias_liquidacao" min="0" max="365" class="form-control @error('dias_liquidacao') is-invalid @enderror" value="{{ old('dias_liquidacao', $entidade?->dias_liquidacao ?? 0) }}">
                @error('dias_liquidacao') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4" id="fp-taxa-plana-wrap">
                <label class="form-label">
                    Taxa (%)
                    <x-label-info content="Taxa (MDR) descontada pelo adquirente. Para crédito com parcelamento, use as faixas por número de parcelas abaixo." />
                </label>
                <input type="number" name="taxa_percentual" step="0.01" min="0" max="100" class="form-control @error('taxa_percentual') is-invalid @enderror" value="{{ old('taxa_percentual', $entidade?->taxa_percentual ?? 0) }}">
                @error('taxa_percentual') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4 d-flex align-items-center pt-2">
                <div class="form-check">
                    <input type="hidden" name="permite_parcelas" value="0">
                    <input type="checkbox" name="permite_parcelas" value="1" class="form-check-input" id="fp-permite-parcelas" {{ old('permite_parcelas', $entidade?->permite_parcelas ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="fp-permite-parcelas">
                        Permite parcelamento
                        <x-label-info content="Cartão de crédito parcelado. O nº de parcelas define a faixa de taxa e a agenda dos recebíveis (D+30, D+60...)." />
                    </label>
                </div>
            </div>
            <div class="col-md-4" id="fp-max-parcelas-wrap">
                <label class="form-label">Máx. de parcelas</label>
                <input type="number" name="max_parcelas" min="1" max="60" class="form-control @error('max_parcelas') is-invalid @enderror" value="{{ old('max_parcelas', $entidade?->max_parcelas) }}" placeholder="Ex.: 12">
                @error('max_parcelas') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

<div class="card stretch stretch-full" id="fp-faixas-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Taxas por faixa de parcelas</h5>
        <button type="button" class="btn btn-sm btn-light" id="fp-add-faixa"><i class="feather-plus me-1"></i>Adicionar faixa</button>
    </div>
    <div class="card-body">
        @error('taxas') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
        <p class="text-muted fs-12 mb-3">Ex.: 1 a 1 → 3,20% · 2 a 6 → 3,80% · 7 a 12 → 4,50%. Se não houver faixa para o número de parcelas, usa-se a taxa plana acima.</p>
        <div id="fp-faixas">
            @foreach($faixas as $i => $faixa)
            <div class="row g-2 align-items-end mb-2 fp-faixa-row">
                <div class="col-md-3">
                    <label class="form-label fs-12">De (parcelas)</label>
                    <input type="number" name="taxas[{{ $i }}][parcela_min]" min="1" max="60" class="form-control" value="{{ data_get($faixa, 'parcela_min') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-12">Até (parcelas)</label>
                    <input type="number" name="taxas[{{ $i }}][parcela_max]" min="1" max="60" class="form-control" value="{{ data_get($faixa, 'parcela_max') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fs-12">Taxa (%)</label>
                    <input type="number" name="taxas[{{ $i }}][taxa_percentual]" step="0.01" min="0" max="100" class="form-control" value="{{ data_get($faixa, 'taxa_percentual') }}">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-light text-danger w-100 fp-remove-faixa"><i class="feather-trash-2"></i></button>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

@push('js')
<script>
(function () {
    const tipo = document.getElementById('fp-tipo');
    const geraRecebivel = document.getElementById('fp-gera-recebivel');
    const permiteParcelas = document.getElementById('fp-permite-parcelas');
    const diasInput = document.querySelector('input[name="dias_liquidacao"]');
    const faixasCard = document.getElementById('fp-faixas-card');
    const maxParcelasWrap = document.getElementById('fp-max-parcelas-wrap');
    const taxaPlanaWrap = document.getElementById('fp-taxa-plana-wrap');
    const faixas = document.getElementById('fp-faixas');
    const addBtn = document.getElementById('fp-add-faixa');
    const isEdit = {{ isset($entidade) ? 'true' : 'false' }};
    let idx = {{ count($faixas) }};

    function refreshParcelas() {
        const on = permiteParcelas.checked;
        faixasCard.style.display = on ? '' : 'none';
        maxParcelasWrap.style.display = on ? '' : 'none';
        taxaPlanaWrap.style.display = on ? 'none' : '';
    }

    function aplicarPadraoTipo() {
        // Só prefila comportamento no cadastro (não sobrescreve edição).
        if (isEdit) return;
        const opt = tipo.options[tipo.selectedIndex];
        geraRecebivel.checked = opt.dataset.geraRecebivel === '1';
        permiteParcelas.checked = opt.dataset.permiteParcelas === '1';
        diasInput.value = opt.dataset.dias;
        refreshParcelas();
    }

    function novaFaixa() {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end mb-2 fp-faixa-row';
        row.innerHTML =
            '<div class="col-md-3"><label class="form-label fs-12">De (parcelas)</label><input type="number" name="taxas[' + idx + '][parcela_min]" min="1" max="60" class="form-control"></div>' +
            '<div class="col-md-3"><label class="form-label fs-12">Até (parcelas)</label><input type="number" name="taxas[' + idx + '][parcela_max]" min="1" max="60" class="form-control"></div>' +
            '<div class="col-md-4"><label class="form-label fs-12">Taxa (%)</label><input type="number" name="taxas[' + idx + '][taxa_percentual]" step="0.01" min="0" max="100" class="form-control"></div>' +
            '<div class="col-md-2"><button type="button" class="btn btn-light text-danger w-100 fp-remove-faixa"><i class="feather-trash-2"></i></button></div>';
        faixas.appendChild(row);
        idx++;
    }

    tipo.addEventListener('change', aplicarPadraoTipo);
    permiteParcelas.addEventListener('change', refreshParcelas);
    addBtn.addEventListener('click', novaFaixa);
    faixas.addEventListener('click', function (e) {
        const btn = e.target.closest('.fp-remove-faixa');
        if (btn) btn.closest('.fp-faixa-row').remove();
    });

    refreshParcelas();
})();
</script>
@endpush
