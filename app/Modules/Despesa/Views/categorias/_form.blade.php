@php $entidade = $entidade ?? null; @endphp

<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">{{ isset($entidade) ? 'Editar' : 'Cadastrar' }} Categoria</h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <label class="form-label">Descrição <span class="text-danger">*</span></label>
                <input type="text" name="descricao" class="form-control @error('descricao') is-invalid @enderror" value="{{ old('descricao', $entidade?->descricao) }}" maxlength="100" required>
                @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3 d-flex align-items-center pt-4">
                <div class="form-check">
                    <input type="hidden" name="ativo" value="0">
                    <input type="checkbox" name="ativo" value="1" class="form-check-input" id="ativo" {{ old('ativo', $entidade?->ativo ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="ativo">Categoria ativa</label>
                </div>
            </div>
        </div>
    </div>
</div>
