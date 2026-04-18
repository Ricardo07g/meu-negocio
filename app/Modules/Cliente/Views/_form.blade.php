@php $entidade = $entidade ?? null; @endphp

{{-- Dados Pessoais --}}
<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">Dados Pessoais</h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-12">
                <label class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $entidade?->nome) }}" maxlength="200" required>
                @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label">Data de Nascimento</label>
                <input type="text" name="data_nascimento" class="form-control mask-data @error('data_nascimento') is-invalid @enderror" value="{{ old('data_nascimento', $entidade?->data_nascimento?->format('d/m/Y')) }}" placeholder="DD/MM/AAAA">
                @error('data_nascimento') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">CPF</label>
                <input type="text" name="cpf" class="form-control mask-cpf @error('cpf') is-invalid @enderror" value="{{ old('cpf', $entidade?->cpf) }}" placeholder="000.000.000-00">
                @error('cpf') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Sexo</label>
                <select name="sexo" class="form-select @error('sexo') is-invalid @enderror">
                    <option value="">Selecione...</option>
                    <option value="M" {{ old('sexo', $entidade?->sexo) == 'M' ? 'selected' : '' }}>Masculino</option>
                    <option value="F" {{ old('sexo', $entidade?->sexo) == 'F' ? 'selected' : '' }}>Feminino</option>
                    <option value="outro" {{ old('sexo', $entidade?->sexo) == 'outro' ? 'selected' : '' }}>Outro</option>
                </select>
                @error('sexo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

{{-- Contato --}}
<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">Contato</h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <label class="form-label">Telefone</label>
                <div class="d-flex align-items-center gap-3">
                    <input type="text" name="telefone" class="form-control mask-telefone @error('telefone') is-invalid @enderror" value="{{ old('telefone', $entidade?->telefone) }}" placeholder="(00) 00000-0000">
                    <div class="form-check text-nowrap mb-0">
                        <input type="hidden" name="telefone_whatsapp" value="0">
                        <input type="checkbox" name="telefone_whatsapp" value="1" class="form-check-input" id="telefone_whatsapp" {{ old('telefone_whatsapp', $entidade?->telefone_whatsapp) ? 'checked' : '' }}>
                        <label class="form-check-label" for="telefone_whatsapp">Este telefone é WhatsApp?</label>
                    </div>
                </div>
                @error('telefone') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $entidade?->email) }}">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

{{-- Endereco --}}
<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">Endereco</h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-3">
                <label class="form-label">CEP</label>
                <div class="input-group">
                    <input type="text" name="cep" id="cep" class="form-control mask-cep @error('cep') is-invalid @enderror" value="{{ old('cep', $entidade?->cep) }}" placeholder="00000-000">
                    <span class="input-group-text" id="btn-buscar-cep" style="cursor: pointer;">
                        <i class="feather-search"></i> Buscar
                    </span>
                </div>
                @error('cep') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Logradouro</label>
                <input type="text" name="logradouro" id="logradouro" class="form-control @error('logradouro') is-invalid @enderror" value="{{ old('logradouro', $entidade?->logradouro) }}">
                @error('logradouro') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Numero</label>
                <input type="text" name="numero" class="form-control @error('numero') is-invalid @enderror" value="{{ old('numero', $entidade?->numero) }}">
                @error('numero') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label">Complemento</label>
                <input type="text" name="complemento" class="form-control @error('complemento') is-invalid @enderror" value="{{ old('complemento', $entidade?->complemento) }}">
                @error('complemento') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Bairro</label>
                <input type="text" name="bairro" id="bairro" class="form-control @error('bairro') is-invalid @enderror" value="{{ old('bairro', $entidade?->bairro) }}">
                @error('bairro') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Cidade</label>
                <input type="text" name="cidade" id="cidade" class="form-control @error('cidade') is-invalid @enderror" value="{{ old('cidade', $entidade?->cidade) }}">
                @error('cidade') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select name="estado" id="estado" class="form-select @error('estado') is-invalid @enderror">
                    <option value="">UF</option>
                    @foreach(['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'] as $uf)
                        <option value="{{ $uf }}" {{ old('estado', $entidade?->estado) == $uf ? 'selected' : '' }}>{{ $uf }}</option>
                    @endforeach
                </select>
                @error('estado') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

{{-- Observacoes --}}
<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">Observacoes</h5>
    </div>
    <div class="card-body">
        <div class="mb-0">
            <label class="form-label">Observacoes</label>
            <textarea name="observacoes" class="form-control @error('observacoes') is-invalid @enderror" rows="3">{{ old('observacoes', $entidade?->observacoes) }}</textarea>
            @error('observacoes') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</div>
