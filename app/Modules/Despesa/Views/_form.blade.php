@php
    $entidade = $entidade ?? null;
    $isCreate = !isset($entidade);
    $hoje = now()->format('Y-m-d');
    $primeiroDoMes = now()->startOfMonth()->format('Y-m-d');
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
                <label class="form-label">Valor (R$) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0.01" name="valor" class="form-control @error('valor') is-invalid @enderror" value="{{ old('valor', $entidade?->valor) }}" required>
                @error('valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Data de Emissão <span class="text-danger">*</span></label>
                <input type="date" name="data_emissao" class="form-control @error('data_emissao') is-invalid @enderror" value="{{ old('data_emissao', $entidade?->data_emissao?->format('Y-m-d') ?? $hoje) }}" required>
                @error('data_emissao') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Data de Vencimento <span class="text-danger">*</span></label>
                <input type="date" name="data_vencimento" class="form-control @error('data_vencimento') is-invalid @enderror" value="{{ old('data_vencimento', $entidade?->data_vencimento?->format('Y-m-d') ?? $hoje) }}" required>
                @error('data_vencimento') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Competência <span class="text-danger">*</span></label>
                <input type="date" name="competencia" class="form-control @error('competencia') is-invalid @enderror" value="{{ old('competencia', $entidade?->competencia?->format('Y-m-d') ?? $primeiroDoMes) }}" required>
                <div class="form-text">Mês de referência contábil (use o dia 1 do mês).</div>
                @error('competencia') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

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
        <h5 class="card-title">Parcelamento</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="parcelar" value="0">
                    <input type="checkbox" name="parcelar" value="1" class="form-check-input" id="parcelar" {{ old('parcelar') ? 'checked' : '' }} onchange="document.getElementById('bloco-parcelas').style.display = this.checked ? 'block' : 'none';">
                    <label class="form-check-label" for="parcelar">Parcelar esta despesa</label>
                </div>
            </div>
            <div class="col-md-4" id="bloco-parcelas" style="{{ old('parcelar') ? '' : 'display:none;' }}">
                <label class="form-label">Número de parcelas <span class="text-danger">*</span></label>
                <input type="number" min="2" max="60" name="numero_parcelas" class="form-control @error('numero_parcelas') is-invalid @enderror" value="{{ old('numero_parcelas') }}">
                <div class="form-text">O valor total será dividido em N parcelas com vencimento mensal a partir da data de vencimento acima.</div>
                @error('numero_parcelas') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>
@endif
