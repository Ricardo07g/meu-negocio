@php
    $entidade = $entidade ?? null;
    $protegida = $entidade?->ehProtegida() ?? false;
@endphp

<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">{{ isset($entidade) ? 'Editar' : 'Cadastrar' }} Conta</h5>
    </div>
    <div class="card-body">
        @if($protegida)
            <div class="alert alert-primary d-flex align-items-center" role="alert">
                <i class="feather-lock me-2"></i>
                <span>Esta é a conta <strong>Caixa</strong> do sistema (a gaveta de dinheiro). É única por empresa e não pode mudar de tipo, ser inativada nem excluída — só o nome pode ser alterado.</span>
            </div>
            <div class="row mb-2">
                <div class="col-md-6">
                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                    <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $entidade?->nome) }}" maxlength="100" required>
                    @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        @else
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                    <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $entidade?->nome) }}" maxlength="100" placeholder="Ex.: Itaú PJ, Mercado Pago" required>
                    @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo <span class="text-danger">*</span></label>
                    <select name="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                        @foreach($tipos as $tipo)
                            @continue($tipo->ehCaixa())
                            <option value="{{ $tipo->value }}" @selected(old('tipo', $entidade?->tipo?->value) === $tipo->value)>{{ $tipo->label() }}</option>
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
                <div class="col-md-4">
                    <label class="form-label">
                        Saldo inicial (R$)
                        <x-label-info content="Quanto já havia nesta conta ao começar a usar o sistema. O saldo atual é este valor + créditos − débitos." />
                    </label>
                    <input type="number" name="saldo_inicial" step="0.01" class="form-control @error('saldo_inicial') is-invalid @enderror" value="{{ old('saldo_inicial', $entidade?->saldo_inicial ?? 0) }}">
                    @error('saldo_inicial') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row mb-2">
                <div class="col-md-4">
                    <label class="form-label">Instituição</label>
                    <input type="text" name="instituicao" class="form-control" value="{{ old('instituicao', $entidade?->instituicao) }}" maxlength="100" placeholder="Ex.: Itaú, Mercado Pago">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Agência</label>
                    <input type="text" name="agencia" class="form-control" value="{{ old('agencia', $entidade?->agencia) }}" maxlength="20">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Número da conta</label>
                    <input type="text" name="numero" class="form-control" value="{{ old('numero', $entidade?->numero) }}" maxlength="30">
                </div>
            </div>
        @endif
    </div>
</div>
