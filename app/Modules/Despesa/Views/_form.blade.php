@php
    $entidade = $entidade ?? null;
    $isCreate = !isset($entidade);
    $hoje = now()->format('Y-m-d');
    $mesAtual = now()->format('Y-m');
    $condAtual = old('condicao_pagamento', 'a_vista');
@endphp

<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">{{ $isCreate ? 'Cadastrar' : 'Editar' }} Despesa</h5>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Nome / Descrição <span class="text-danger">*</span></label>
                <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $entidade?->nome) }}" maxlength="200" required>
                @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Categoria</label>
                <select name="categoria_despesa_id" class="form-select @error('categoria_despesa_id') is-invalid @enderror">
                    <option value="">— selecione —</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" @selected(old('categoria_despesa_id', $entidade?->categoria_despesa_id) == $cat->id)>{{ $cat->descricao }}</option>
                    @endforeach
                </select>
                @error('categoria_despesa_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label">Fornecedor</label>
                <input type="text" name="fornecedor_nome" class="form-control @error('fornecedor_nome') is-invalid @enderror" value="{{ old('fornecedor_nome', $entidade?->fornecedor_nome) }}" maxlength="150">
                @error('fornecedor_nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Documento / NF</label>
                <input type="text" name="documento" class="form-control @error('documento') is-invalid @enderror" value="{{ old('documento', $entidade?->documento) }}" maxlength="80">
                @error('documento') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Data de Emissão <span class="text-danger">*</span></label>
                <input type="date" name="data_emissao" class="form-control @error('data_emissao') is-invalid @enderror" value="{{ old('data_emissao', $entidade?->data_emissao?->format('Y-m-d') ?? $hoje) }}" required>
                @error('data_emissao') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Mês de Referência <span class="text-danger">*</span></label>
                <input type="month" name="mes_referencia" class="form-control @error('mes_referencia') is-invalid @enderror" value="{{ old('mes_referencia', $entidade?->mes_referencia?->format('Y-m') ?? $mesAtual) }}" required>
                <div class="form-text">Competência contábil (mês a que a despesa se refere).</div>
                @error('mes_referencia') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            @if($isCreate)
            <div class="col-md-4">
                <label class="form-label">Valor Total (R$) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0.01" name="valor_total" id="valorTotalDespesa" class="form-control @error('valor_total') is-invalid @enderror" value="{{ old('valor_total') }}" required>
                @error('valor_total') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            @endif

            <div class="col-12">
                <label class="form-label">Observações</label>
                <textarea name="observacoes" rows="3" class="form-control @error('observacoes') is-invalid @enderror">{{ old('observacoes', $entidade?->observacoes) }}</textarea>
                @error('observacoes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

@if($isCreate)
<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">Pagamento</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Condição de Pagamento <span class="text-danger">*</span></label>
                <select name="condicao_pagamento" id="despCondicaoSelect" class="form-select @error('condicao_pagamento') is-invalid @enderror">
                    <option value="a_vista" @selected($condAtual === 'a_vista')>À Vista</option>
                    <option value="a_prazo" @selected($condAtual === 'a_prazo')>A Prazo</option>
                </select>
                @error('condicao_pagamento') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4" id="despFormaWrapper">
                <label class="form-label" id="despFormaLabel">Forma de Pagamento <span class="text-danger">*</span></label>
                <select name="forma_pagamento" class="form-select @error('forma_pagamento') is-invalid @enderror">
                    <option value="">Selecione...</option>
                    @foreach(($formas ?? []) as $forma)
                        <option value="{{ $forma->id }}" {{ (int) old('forma_pagamento') === $forma->id ? 'selected' : '' }}>{{ $forma->nome }}</option>
                    @endforeach
                </select>
                @error('forma_pagamento') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4" id="despParcelasWrapper" style="display:none;">
                <label class="form-label">Número de Parcelas <span class="text-danger">*</span></label>
                <input type="number" min="2" max="24" name="numero_parcelas" id="despNumParcelas" class="form-control @error('numero_parcelas') is-invalid @enderror" value="{{ old('numero_parcelas', 2) }}">
                <div class="form-text" id="despValorPorParcelaHint"></div>
                @error('numero_parcelas') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Primeiro Vencimento <span class="text-danger">*</span></label>
                <input type="date" name="primeiro_vencimento" id="despPrimeiroVencimento" class="form-control @error('primeiro_vencimento') is-invalid @enderror" value="{{ old('primeiro_vencimento', $hoje) }}" required>
                <div class="form-text">Para parcelamento, as demais parcelas vencem mensalmente a partir daqui.</div>
                @error('primeiro_vencimento') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

{{-- Preview das parcelas (só quando condicao=a_prazo) --}}
<div class="card stretch stretch-full mt-4" id="despPreviewParcelasCard" style="display:none;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Preview das Parcelas <span id="despPreviewBadge" class="badge bg-warning ms-2"></span></h5>
        <span id="despDiferencaBadge" class="badge bg-soft-danger text-danger" style="display:none;">
            <i class="feather-alert-circle me-1"></i><span id="despDiferencaTexto"></span>
        </span>
    </div>
    <div class="card-body">
        <p class="text-muted fs-13 mb-3">Ajuste valor, vencimento e competência de cada parcela se necessário. A soma precisa bater com o valor total.</p>
        <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
            <table class="table table-hover mb-0 align-middle" id="despTabelaParcelas">
                <thead class="position-sticky top-0 bg-white" style="z-index:1;">
                    <tr>
                        <th style="width:60px;">#</th>
                        <th>Vencimento</th>
                        <th>Competência</th>
                        <th class="text-end">Valor</th>
                    </tr>
                </thead>
                <tbody id="despParcelasTbody"></tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td colspan="3" class="text-end">Total:</td>
                        <td class="text-end" id="despParcelasTotalFoot">R$ 0,00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const formaWrapper = document.getElementById('despFormaWrapper');
    const formaLabel = document.getElementById('despFormaLabel');
    const parcelasWrapper = document.getElementById('despParcelasWrapper');
    const numParcelas = document.getElementById('despNumParcelas');
    const valorTotal = document.getElementById('valorTotalDespesa');
    const hint = document.getElementById('despValorPorParcelaHint');
    const primeiroVencimento = document.getElementById('despPrimeiroVencimento');
    const mesReferencia = document.querySelector('input[name="mes_referencia"]');

    const previewCard = document.getElementById('despPreviewParcelasCard');
    const previewBadge = document.getElementById('despPreviewBadge');
    const previewTbody = document.getElementById('despParcelasTbody');
    const previewTotalFoot = document.getElementById('despParcelasTotalFoot');
    const diferencaBadge = document.getElementById('despDiferencaBadge');
    const diferencaTexto = document.getElementById('despDiferencaTexto');

    const condicaoSelect = document.getElementById('despCondicaoSelect');

    function condicao() {
        return condicaoSelect ? condicaoSelect.value : 'a_vista';
    }

    function formatarBR(v) { return v.toFixed(2).replace('.', ','); }

    function adicionarMeses(base, meses) {
        return new Date(base.getFullYear(), base.getMonth() + meses, base.getDate());
    }

    function aplicar() {
        const c = condicao();
        const aVista = c === 'a_vista';
        const aPrazo = c === 'a_prazo';

        parcelasWrapper.style.display = aPrazo ? '' : 'none';
        numParcelas.disabled = !aPrazo;
        previewCard.style.display = aPrazo ? '' : 'none';

        if (formaLabel) {
            formaLabel.innerHTML = aVista
                ? 'Forma da baixa à vista <span class="text-danger">*</span>'
                : 'Forma de pagamento prevista <span class="text-danger">*</span>';
        }

        atualizarPreview();
    }

    function recalcularSoma() {
        const inputs = previewTbody.querySelectorAll('input[data-parcela-valor]');
        let soma = 0;
        inputs.forEach(function (el) { soma += parseFloat(el.value) || 0; });
        previewTotalFoot.textContent = 'R$ ' + formatarBR(soma);

        const total = parseFloat(valorTotal?.value) || 0;
        const diff = Math.round((soma - total) * 100) / 100;
        if (Math.abs(diff) < 0.01 || total <= 0) {
            diferencaBadge.style.display = 'none';
        } else {
            diferencaBadge.style.display = '';
            diferencaTexto.textContent = (diff > 0 ? 'Excedendo' : 'Faltando') + ' R$ ' + formatarBR(Math.abs(diff));
        }
    }

    function atualizarPreview() {
        if (condicao() !== 'a_prazo') {
            hint.textContent = '';
            previewBadge.textContent = '';
            previewTbody.innerHTML = '';
            previewTotalFoot.textContent = 'R$ 0,00';
            return;
        }

        const n = parseInt(numParcelas.value) || 0;
        const total = parseFloat(valorTotal?.value) || 0;

        if (n < 2 || n > 24) {
            hint.textContent = 'Informe entre 2 e 24 parcelas.';
            previewTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Informe 2 a 24 parcelas.</td></tr>';
            previewBadge.textContent = '';
            previewTotalFoot.textContent = 'R$ 0,00';
            return;
        }
        if (total <= 0) {
            hint.textContent = '';
            previewTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Informe o valor total para simular as parcelas.</td></tr>';
            previewBadge.textContent = n + 'x';
            previewTotalFoot.textContent = 'R$ 0,00';
            return;
        }
        const vencRaw = primeiroVencimento.value;
        if (!vencRaw) {
            previewTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Informe o primeiro vencimento.</td></tr>';
            return;
        }

        const porParcela = Math.round((total / n) * 100) / 100;
        const ultima = Math.round((total - porParcela * (n - 1)) * 100) / 100;

        hint.textContent = n + 'x de R$ ' + formatarBR(porParcela);
        previewBadge.textContent = n + ' parcelas';

        const baseVenc = new Date(vencRaw + 'T12:00:00');
        const linhas = [];
        for (let i = 1; i <= n; i++) {
            const venc = adicionarMeses(baseVenc, i - 1);
            const vencIso = venc.getFullYear() + '-' +
                String(venc.getMonth() + 1).padStart(2, '0') + '-' +
                String(venc.getDate()).padStart(2, '0');
            // Competencia da parcela = mes do seu vencimento.
            const mesRefParcela = vencIso.substring(0, 7);
            const val = i === n ? ultima : porParcela;
            const idx = i - 1;
            linhas.push(
                '<tr>' +
                '<td><span class="fw-semibold">' + i + '/' + n + '</span>' +
                '<input type="hidden" name="parcelas[' + idx + '][numero]" value="' + i + '">' +
                '<input type="hidden" name="parcelas[' + idx + '][total]" value="' + n + '">' +
                '</td>' +
                '<td><input type="date" name="parcelas[' + idx + '][data_vencimento]" ' +
                '       class="form-control form-control-sm" data-parcela-vencimento value="' + vencIso + '" required></td>' +
                '<td><input type="month" name="parcelas[' + idx + '][mes_referencia]" ' +
                '       class="form-control form-control-sm" data-parcela-mes-ref value="' + mesRefParcela + '" required></td>' +
                '<td class="text-end"><input type="number" step="0.01" min="0.01" data-parcela-valor ' +
                '       name="parcelas[' + idx + '][valor]" ' +
                '       class="form-control form-control-sm text-end" value="' + val.toFixed(2) + '" required></td>' +
                '</tr>'
            );
        }
        previewTbody.innerHTML = linhas.join('');

        previewTbody.querySelectorAll('input[data-parcela-valor]').forEach(function (el) {
            el.addEventListener('input', recalcularSoma);
        });

        // Vencimento -> sincroniza competencia da parcela
        previewTbody.querySelectorAll('input[data-parcela-vencimento]').forEach(function (el) {
            el.addEventListener('change', function () {
                const tr = el.closest('tr');
                const mesRefInput = tr.querySelector('input[data-parcela-mes-ref]');
                if (mesRefInput && el.value) {
                    mesRefInput.value = el.value.substring(0, 7);
                }
            });
        });

        recalcularSoma();
    }

    if (condicaoSelect) condicaoSelect.addEventListener('change', aplicar);
    numParcelas.addEventListener('input', atualizarPreview);
    if (primeiroVencimento) primeiroVencimento.addEventListener('change', atualizarPreview);
    if (mesReferencia) mesReferencia.addEventListener('change', atualizarPreview);
    if (valorTotal) valorTotal.addEventListener('input', atualizarPreview);
    aplicar();
});
</script>
@endpush
@endif
