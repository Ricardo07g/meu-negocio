@php $entidade = $entidade ?? null; @endphp

{{-- Identificação --}}
<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">Identificação</h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-12">
                <label class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $entidade?->nome) }}" required>
                @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label">Código</label>
                <input type="text" name="codigo" class="form-control @error('codigo') is-invalid @enderror" value="{{ old('codigo', $entidade?->codigo) }}">
                @error('codigo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Código de Barras</label>
                <input type="text" name="codigo_barras" class="form-control @error('codigo_barras') is-invalid @enderror" value="{{ old('codigo_barras', $entidade?->codigo_barras) }}">
                @error('codigo_barras') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Categoria</label>
                <select name="categoria_produto_id" class="form-select @error('categoria_produto_id') is-invalid @enderror">
                    <option value="">Selecione...</option>
                    @foreach($categorias as $categoria)
                        <option value="{{ $categoria->id }}" {{ old('categoria_produto_id', $entidade?->categoria_produto_id) == $categoria->id ? 'selected' : '' }}>{{ $categoria->descricao }}</option>
                    @endforeach
                </select>
                @error('categoria_produto_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="row mb-0">
            <div class="col-12">
                <label class="form-label">Descrição</label>
                <textarea name="descricao" class="form-control @error('descricao') is-invalid @enderror" rows="2">{{ old('descricao', $entidade?->descricao) }}</textarea>
                @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

{{-- Preços --}}
<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">Preços</h5>
    </div>
    <div class="card-body">
        <div class="row mb-0">
            <div class="col-md-4">
                <label class="form-label">Preço de Custo (R$)</label>
                <input type="number" name="valor_custo" class="form-control @error('valor_custo') is-invalid @enderror" value="{{ old('valor_custo', $entidade?->valor_custo) }}" step="0.01" min="0">
                @error('valor_custo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Preço de Venda (R$) <span class="text-danger">*</span></label>
                <input type="number" name="valor_venda" class="form-control @error('valor_venda') is-invalid @enderror" value="{{ old('valor_venda', $entidade?->valor_venda) }}" step="0.01" min="0" required>
                @error('valor_venda') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4"></div>
        </div>
    </div>
</div>

{{-- Estoque --}}
<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">Estoque</h5>
    </div>
    <div class="card-body">
        <div class="row mb-0">
            <div class="col-md-3">
                <label class="form-label">Quantidade <span class="text-danger">*</span></label>
                <input type="number" name="quantidade" class="form-control @error('quantidade') is-invalid @enderror" value="{{ old('quantidade', $entidade?->quantidade ?? 0) }}" min="0" required>
                @error('quantidade') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Estoque Mínimo</label>
                <input type="number" name="estoque_minimo" class="form-control @error('estoque_minimo') is-invalid @enderror" value="{{ old('estoque_minimo', $entidade?->estoque_minimo) }}" min="0">
                @error('estoque_minimo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Unidade</label>
                <select name="unidade" class="form-select @error('unidade') is-invalid @enderror">
                    <option value="">Selecione...</option>
                    @foreach(['un' => 'Unidade (un)', 'kg' => 'Quilograma (kg)', 'g' => 'Grama (g)', 'ml' => 'Mililitro (ml)', 'L' => 'Litro (L)', 'cx' => 'Caixa (cx)', 'pct' => 'Pacote (pct)'] as $sigla => $label)
                        <option value="{{ $sigla }}" {{ old('unidade', $entidade?->unidade) == $sigla ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('unidade') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3 d-flex align-items-center pt-4">
                <div class="form-check">
                    <input type="hidden" name="ativo" value="0">
                    <input type="checkbox" name="ativo" value="1" class="form-check-input" id="ativo" {{ old('ativo', $entidade?->ativo ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="ativo">Produto ativo</label>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Observações --}}
<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">Observações</h5>
    </div>
    <div class="card-body">
        <div class="mb-0">
            <label class="form-label">Observações</label>
            <textarea name="observacoes" class="form-control @error('observacoes') is-invalid @enderror" rows="3">{{ old('observacoes', $entidade?->observacoes) }}</textarea>
            @error('observacoes') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</div>
