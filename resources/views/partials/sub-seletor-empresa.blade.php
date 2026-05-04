{{--
    Sub-seletor de empresa (ME-010 v2).

    Renderiza um <select> de empresa em formularios transacionais para deixar
    explicito a qual empresa o registro pertence, mesmo quando o usuario tem
    apenas 1 empresa selecionada no header global.

    Modos:
      - 'editar' (default): usuario pode escolher entre as empresas atuais.
        Com 1 empresa: select disabled + hidden input (selects disabled nao
        submetem o valor; o hidden garante que `empresa_id` chegue ao request).
        Com N empresas: select normal (required).
      - 'visualizar': mostra apenas a empresa do registro origem (read-only).
        Sem hidden input — nao submete. Usado em telas de baixa onde a empresa
        e herdada da parcela.

    Uso:
        @include('partials.sub-seletor-empresa', [
            'valorAtual' => $entidade?->empresa_id, // default: primeira empresa da sessao
            'modo'       => 'editar',                // 'editar' (default) | 'visualizar'
            'colunaCss'  => 'col-md-6',              // default
            'inputId'    => 'empresa_id',            // custom para evitar colisao em modais
            'inputName'  => 'empresa_id',
            'rotulo'     => 'Empresa',
        ])

    Forms que incluirem este partial em modo 'editar' devem aceitar
    'empresa_id' no SalvarXxxRequest e DTO correspondente.
--}}
@php
    use App\Modules\Tenant\Models\Empresa;

    $modo = $modo ?? 'editar';
    $colunaCss = $colunaCss ?? 'col-md-6';
    $inputId = $inputId ?? 'empresa_id';
    $inputName = $inputName ?? 'empresa_id';
    $rotulo = $rotulo ?? 'Empresa';

    $empresasAtuais = collect(session('empresas_atuais', []))->map(fn ($id) => (int) $id);

    if ($modo === 'visualizar') {
        // Read-only: mostra apenas a empresa do valorAtual.
        $idAlvo = (int) ($valorAtual ?? 0);
        $opcoes = $idAlvo > 0
            ? Empresa::query()->whereIn('id', [$idAlvo])->orderBy('nome')->get(['id', 'nome'])
            : collect();
        $valorSelecionado = $idAlvo;
    } else {
        // Editar: lista as empresas atuais da sessao.
        if ($empresasAtuais->isEmpty()) {
            return;
        }
        $opcoes = Empresa::query()
            ->whereIn('id', $empresasAtuais->all())
            ->orderBy('nome')
            ->get(['id', 'nome']);
        $valorSelecionado = (int) old($inputName, $valorAtual ?? $empresasAtuais->first());
    }

    $apenasUma = $opcoes->count() === 1;
    $bloqueado = $modo === 'visualizar' || ($modo === 'editar' && $apenasUma);
@endphp

@if ($opcoes->isNotEmpty())
    <div class="{{ $colunaCss }} mb-3">
        <label for="{{ $inputId }}" class="form-label">
            {{ $rotulo }}
            @if ($modo === 'editar')
                <span class="text-danger">*</span>
            @endif
        </label>
        <select
            id="{{ $inputId }}"
            class="form-select @error($inputName) is-invalid @enderror{{ $bloqueado ? ' bg-light text-muted' : '' }}"
            @if ($modo === 'editar' && ! $apenasUma) name="{{ $inputName }}" required @endif
            @if ($bloqueado) disabled @endif
        >
            @foreach ($opcoes as $opcao)
                <option value="{{ $opcao->id }}" @selected((int) $valorSelecionado === (int) $opcao->id)>
                    {{ $opcao->nome }}
                </option>
            @endforeach
        </select>
        @if ($modo === 'editar' && $apenasUma)
            <input type="hidden" name="{{ $inputName }}" value="{{ $valorSelecionado }}">
        @endif
        @error($inputName)
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
@endif
