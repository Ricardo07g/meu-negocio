{{--
    Sub-seletor de empresa (ME-010).

    Renderiza um <select> de empresa quando ha mais de uma empresa selecionada
    no header (session('empresas_atuais')). Quando ha apenas 1, nao renderiza
    nada — o EmpresaTrait resolve automaticamente. Quando o usuario nao tem
    multiplas empresas acessiveis (ex.: nao-admin com 1 empresa no pivot),
    tambem nao renderiza.

    Uso:
        @include('partials.sub-seletor-empresa', [
            'valorAtual' => $entidade?->empresa_id,
            'colunaCss' => 'col-md-6', // opcional
        ])

    Forms que incluirem este partial devem aceitar 'empresa_id' no
    SalvarXxxRequest e DTO correspondente.
--}}
@php
    use App\Modules\Tenant\Models\Empresa;

    $empresasAtuais = collect(session('empresas_atuais', []))->map(fn ($id) => (int) $id);

    if ($empresasAtuais->count() <= 1) {
        return;
    }

    $opcoes = Empresa::query()
        ->whereIn('id', $empresasAtuais->all())
        ->orderBy('nome')
        ->get(['id', 'nome']);

    $colunaCss = $colunaCss ?? 'col-md-6';
    $valorAtual = old('empresa_id', $valorAtual ?? $empresasAtuais->first());
@endphp

<div class="{{ $colunaCss }} mb-3">
    <label for="empresa_id" class="form-label">
        Empresa <span class="text-danger">*</span>
    </label>
    <select name="empresa_id" id="empresa_id" class="form-select @error('empresa_id') is-invalid @enderror" required>
        @foreach ($opcoes as $opcao)
            <option value="{{ $opcao->id }}" @selected((int) $valorAtual === (int) $opcao->id)>
                {{ $opcao->nome }}
            </option>
        @endforeach
    </select>
    @error('empresa_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
