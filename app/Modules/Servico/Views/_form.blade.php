@php $entidade = $entidade ?? null; @endphp

{{-- Imagem --}}
<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">Imagem</h5>
    </div>
    <div class="card-body">
        <x-campo-imagem :atual="$entidade?->imagem_thumb_url" label="Imagem do serviço" />
    </div>
</div>

<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">{{ isset($entidade) ? 'Editar' : 'Cadastrar' }} Serviço</h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-3">
                <label class="form-label">Tipo <span class="text-danger">*</span></label>
                <select name="tipo" id="tipoServico" class="form-select @error('tipo') is-invalid @enderror" required>
                    <option value="unico" {{ old('tipo', $entidade?->tipo?->value ?? 'unico') === 'unico' ? 'selected' : '' }}>Serviço Único</option>
                    <option value="etapas" {{ old('tipo', $entidade?->tipo?->value ?? 'unico') === 'etapas' ? 'selected' : '' }}>Serviço em Etapas</option>
                </select>
                @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $entidade?->nome) }}" required>
                @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Duração por sessão (min) <span class="text-danger">*</span></label>
                <input type="number" name="duracao" class="form-control @error('duracao') is-invalid @enderror" value="{{ old('duracao', $entidade?->duracao) }}" min="1" required>
                @error('duracao') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Valor (R$) <span class="text-danger">*</span></label>
                <input type="number" name="valor" class="form-control @error('valor') is-invalid @enderror" value="{{ old('valor', $entidade?->valor) }}" step="0.01" min="0" required>
                @error('valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        {{-- Campos de etapas --}}
        <div id="camposEtapas" style="{{ old('tipo', $entidade?->tipo?->value ?? 'unico') === 'etapas' ? '' : 'display:none;' }}">
            <div class="row mb-4">
                <div class="col-md-3">
                    <label class="form-label">Quantidade de Etapas <span class="text-danger">*</span></label>
                    <input type="number" name="qtd_etapas" class="form-control @error('qtd_etapas') is-invalid @enderror" value="{{ old('qtd_etapas', $entidade?->qtd_etapas) }}" min="2">
                    @error('qtd_etapas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-9">
                    <label class="form-label">Descrição</label>
                    <input type="text" name="descricao" class="form-control @error('descricao') is-invalid @enderror" value="{{ old('descricao', $entidade?->descricao) }}" placeholder="Ex: 10 sessões de massagem relaxante">
                    @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
document.getElementById('tipoServico').addEventListener('change', function() {
    document.getElementById('camposEtapas').style.display = this.value === 'etapas' ? '' : 'none';
});
</script>
@endpush
