<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">{{ isset($entidade) ? 'Editar' : 'Cadastrar' }} Despesa</h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $entidade?->nome) }}" required>
                @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Valor (R$) <span class="text-danger">*</span></label>
                <input type="number" name="valor" class="form-control @error('valor') is-invalid @enderror" value="{{ old('valor', $entidade?->valor) }}" step="0.01" min="0" required>
                @error('valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Data <span class="text-danger">*</span></label>
                <input type="date" name="data" class="form-control @error('data') is-invalid @enderror" value="{{ old('data', $entidade?->data?->format('Y-m-d') ?? date('Y-m-d')) }}" required>
                @error('data') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>
