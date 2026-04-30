@php $entidade = $entidade ?? null; @endphp

<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">Reagendar Agendamento</h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <label class="form-label">Cliente <span class="text-danger">*</span></label>
                <select name="cliente_id" class="form-select @error('cliente_id') is-invalid @enderror" required>
                    <option value="">Selecione...</option>
                    @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" {{ old('cliente_id', $entidade?->cliente_id) == $cliente->id ? 'selected' : '' }}>{{ $cliente->nome }}</option>
                    @endforeach
                </select>
                @error('cliente_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Serviço <span class="text-danger">*</span></label>
                <select name="servico_id" class="form-select @error('servico_id') is-invalid @enderror" required>
                    <option value="">Selecione...</option>
                    @foreach($servicos as $servico)
                        <option value="{{ $servico->id }}" {{ old('servico_id', $entidade?->servico_id) == $servico->id ? 'selected' : '' }}>{{ $servico->nome }}</option>
                    @endforeach
                </select>
                @error('servico_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label">Atendente <span class="text-danger">*</span></label>
                <select name="atendente_id" class="form-select @error('atendente_id') is-invalid @enderror" required>
                    <option value="">Selecione...</option>
                    @foreach($atendentes as $atendente)
                        <option value="{{ $atendente->id }}" {{ old('atendente_id', $entidade?->atendente_id) == $atendente->id ? 'selected' : '' }}>{{ $atendente->nome }}</option>
                    @endforeach
                </select>
                @error('atendente_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Início <span class="text-danger">*</span></label>
                <input type="datetime-local" name="inicio" class="form-control @error('inicio') is-invalid @enderror" value="{{ old('inicio', $entidade?->inicio?->format('Y-m-d\TH:i')) }}" required>
                @error('inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Fim <small class="text-muted">(opcional)</small></label>
                <input type="datetime-local" name="fim" class="form-control @error('fim') is-invalid @enderror" value="{{ old('fim', $entidade?->fim?->format('Y-m-d\TH:i')) }}">
                @error('fim') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-12">
                <label class="form-label">Observações</label>
                <textarea name="observacoes" class="form-control @error('observacoes') is-invalid @enderror" rows="3" placeholder="Observações sobre este agendamento...">{{ old('observacoes', $entidade?->observacoes) }}</textarea>
                @error('observacoes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>
