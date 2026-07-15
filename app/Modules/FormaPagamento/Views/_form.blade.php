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
                            data-dias="{{ $tipo->diasLiquidacaoPadrao() }}"
                            data-usa-liquidacao="{{ $tipo->usaLiquidacao() ? 1 : 0 }}"
                            data-usa-taxa-plana="{{ $tipo->usaTaxaPlana() ? 1 : 0 }}"
                            data-usa-faixas="{{ $tipo->usaFaixas() ? 1 : 0 }}"
                            data-usa-antecipacao="{{ $tipo->usaAntecipacao() ? 1 : 0 }}"
                            data-eh-crediario="{{ $tipo->ehCrediario() ? 1 : 0 }}"
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

        {{-- Campos extras: só aparecem para os tipos que os suportam (o servidor normaliza o resto). --}}
        <div id="fp-extra-config">
            <div class="row mb-4">
                <div class="col-md-4" id="fp-liquidacao-wrap">
                    <label class="form-label">
                        Dias para liquidação (D+N)
                        <x-label-info content="Dias até o dinheiro cair (o banco pagar). Débito ≈ 1, crédito ≈ 30." />
                    </label>
                    <input type="number" name="dias_liquidacao" min="0" max="365" class="form-control @error('dias_liquidacao') is-invalid @enderror" value="{{ old('dias_liquidacao', $entidade?->dias_liquidacao ?? 0) }}">
                    @error('dias_liquidacao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4" id="fp-taxa-plana-wrap">
                    <label class="form-label">
                        Taxa (%)
                        <x-label-info content="Taxa (MDR) descontada pelo adquirente no débito. No crédito, use as faixas por número de parcelas abaixo." />
                    </label>
                    <input type="number" name="taxa_percentual" step="0.01" min="0" max="100" class="form-control @error('taxa_percentual') is-invalid @enderror" value="{{ old('taxa_percentual', $entidade?->taxa_percentual ?? 0) }}">
                    @error('taxa_percentual') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4" id="fp-max-parcelas-wrap">
                    <label class="form-label" id="fp-max-parcelas-label">Máx. de parcelas no cartão</label>
                    <input type="number" name="max_parcelas" min="1" max="60" class="form-control @error('max_parcelas') is-invalid @enderror" value="{{ old('max_parcelas', $entidade?->max_parcelas) }}" placeholder="Ex.: 12">
                    @error('max_parcelas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row mb-4" id="fp-antecipacao-wrap">
                <div class="col-md-4 d-flex align-items-center pt-2">
                    <div class="form-check">
                        <input type="hidden" name="antecipacao_automatica" value="0">
                        <input type="checkbox" name="antecipacao_automatica" value="1" class="form-check-input" id="fp-antecipacao-auto" {{ old('antecipacao_automatica', $entidade?->antecipacao_automatica ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="fp-antecipacao-auto">
                            Antecipação automática
                            <x-label-info content="Recebe os valores do adquirente antecipados (quase à vista) em vez de D+30/60/90, cobrando uma taxa mensal. O valor líquido do recebível já desconta esse custo." />
                        </label>
                    </div>
                </div>
                <div class="col-md-4" id="fp-antecipacao-taxa-wrap">
                    <label class="form-label">Taxa de antecipação (% ao mês)</label>
                    <input type="number" name="taxa_antecipacao_mensal" step="0.01" min="0" max="100" class="form-control @error('taxa_antecipacao_mensal') is-invalid @enderror" value="{{ old('taxa_antecipacao_mensal', $entidade?->taxa_antecipacao_mensal ?? 0) }}" placeholder="Ex.: 1,99">
                    @error('taxa_antecipacao_mensal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <p class="text-muted fs-12 mb-0" id="fp-crediario-hint">
                <i class="feather-info me-1"></i>No crediário a loja financia o cliente: a venda vira "a prazo" com as parcelas do cliente (a receber do cliente). Não gera recebível de banco nem taxa de cartão.
            </p>
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
    const isEdit = {{ isset($entidade) ? 'true' : 'false' }};
    let idx = {{ count($faixas) }};

    const extraConfig = document.getElementById('fp-extra-config');
    const liquidacaoWrap = document.getElementById('fp-liquidacao-wrap');
    const taxaPlanaWrap = document.getElementById('fp-taxa-plana-wrap');
    const maxParcelasWrap = document.getElementById('fp-max-parcelas-wrap');
    const maxParcelasLabel = document.getElementById('fp-max-parcelas-label');
    const antecipacaoWrap = document.getElementById('fp-antecipacao-wrap');
    const antecipacaoAuto = document.getElementById('fp-antecipacao-auto');
    const antecipacaoTaxaWrap = document.getElementById('fp-antecipacao-taxa-wrap');
    const crediarioHint = document.getElementById('fp-crediario-hint');
    const faixasCard = document.getElementById('fp-faixas-card');
    const diasInput = document.querySelector('input[name="dias_liquidacao"]');
    const faixas = document.getElementById('fp-faixas');
    const addBtn = document.getElementById('fp-add-faixa');

    function show(el, on) { if (el) el.style.display = on ? '' : 'none'; }
    function opt() { return tipo.options[tipo.selectedIndex]; }

    function refresh() {
        const d = opt().dataset;
        const usaLiquidacao = d.usaLiquidacao === '1';
        const usaTaxaPlana = d.usaTaxaPlana === '1';
        const usaFaixas = d.usaFaixas === '1';
        const usaAntecipacao = d.usaAntecipacao === '1';
        const ehCrediario = d.ehCrediario === '1';
        const usaMaxParcelas = usaFaixas || ehCrediario;

        show(liquidacaoWrap, usaLiquidacao);
        show(taxaPlanaWrap, usaTaxaPlana);
        show(maxParcelasWrap, usaMaxParcelas);
        show(faixasCard, usaFaixas);
        show(antecipacaoWrap, usaAntecipacao);
        show(antecipacaoTaxaWrap, usaAntecipacao && antecipacaoAuto.checked);
        show(crediarioHint, ehCrediario);

        // Extra-config some inteiro para tipos sem campos (dinheiro/pix/boleto).
        show(extraConfig, usaLiquidacao || usaTaxaPlana || usaMaxParcelas || usaAntecipacao || ehCrediario);

        maxParcelasLabel.textContent = ehCrediario ? 'Máx. de parcelas do cliente' : 'Máx. de parcelas no cartão';
    }

    function prefillDias() {
        // Só no cadastro: ao trocar de tipo prefila o D+N padrão (não sobrescreve edição).
        if (!isEdit) diasInput.value = opt().dataset.dias || 0;
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

    tipo.addEventListener('change', function () { prefillDias(); refresh(); });
    antecipacaoAuto.addEventListener('change', refresh);
    addBtn.addEventListener('click', novaFaixa);
    faixas.addEventListener('click', function (e) {
        const btn = e.target.closest('.fp-remove-faixa');
        if (btn) btn.closest('.fp-faixa-row').remove();
    });

    refresh();
})();
</script>
@endpush
