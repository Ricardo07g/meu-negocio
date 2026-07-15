@php
    $entidade = $entidade ?? null;
@endphp

<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">{{ isset($entidade) ? 'Editar' : 'Cadastrar' }} Conta</h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <label class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $entidade?->nome) }}" maxlength="100" placeholder="Ex.: Itaú PJ" required>
                @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Tipo <span class="text-danger">*</span></label>
                <select name="tipo" id="conta-tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                    @foreach($tipos as $tipo)
                        <option value="{{ $tipo->value }}"
                            data-eh-caixa="{{ $tipo->ehCaixa() ? 1 : 0 }}"
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

        <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label">
                    Saldo inicial (R$)
                    <x-label-info content="Quanto já havia nesta conta ao começar a usar o sistema. O saldo atual é este valor + créditos − débitos." />
                </label>
                <input type="number" name="saldo_inicial" step="0.01" class="form-control @error('saldo_inicial') is-invalid @enderror" value="{{ old('saldo_inicial', $entidade?->saldo_inicial ?? 0) }}">
                @error('saldo_inicial') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4 d-flex align-items-center pt-2" id="conta-caixa-padrao-wrap">
                <div class="form-check">
                    <input type="hidden" name="eh_caixa_padrao" value="0">
                    <input type="checkbox" name="eh_caixa_padrao" value="1" class="form-check-input" id="conta-caixa-padrao" {{ old('eh_caixa_padrao', $entidade?->eh_caixa_padrao ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="conta-caixa-padrao">
                        Caixa padrão
                        <x-label-info content="A gaveta de dinheiro físico usada nas vendas em dinheiro. Só uma conta por empresa." />
                    </label>
                </div>
            </div>
            <div class="col-md-4 d-flex align-items-center pt-2" id="conta-destino-wrap">
                <div class="form-check">
                    <input type="hidden" name="eh_destino_recebivel_padrao" value="0">
                    <input type="checkbox" name="eh_destino_recebivel_padrao" value="1" class="form-check-input" id="conta-destino" {{ old('eh_destino_recebivel_padrao', $entidade?->eh_destino_recebivel_padrao ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="conta-destino">
                        Destino dos recebíveis
                        <x-label-info content="Conde cai o dinheiro de cartão/pix. Só uma conta por empresa." />
                    </label>
                </div>
            </div>
        </div>

        <div class="row mb-2" id="conta-banco-wrap">
            <div class="col-md-4">
                <label class="form-label">Instituição</label>
                <input type="text" name="instituicao" class="form-control" value="{{ old('instituicao', $entidade?->instituicao) }}" maxlength="100" placeholder="Ex.: Itaú">
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
    </div>
</div>

@push('js')
<script>
(function () {
    const tipo = document.getElementById('conta-tipo');
    const caixaPadraoWrap = document.getElementById('conta-caixa-padrao-wrap');
    const destinoWrap = document.getElementById('conta-destino-wrap');
    const bancoWrap = document.getElementById('conta-banco-wrap');

    function show(el, on) { if (el) el.style.display = on ? '' : 'none'; }

    function refresh() {
        const ehCaixa = tipo.options[tipo.selectedIndex].dataset.ehCaixa === '1';
        show(caixaPadraoWrap, ehCaixa);
        show(destinoWrap, !ehCaixa);
        show(bancoWrap, !ehCaixa);
    }

    tipo.addEventListener('change', refresh);
    refresh();
})();
</script>
@endpush
