{{--
    Filtro de empresa para listagens transacionais (ME-010 v3).

    Renderiza um <select> com as empresas acessiveis ao usuario, com a
    opcao "Todas as empresas" no topo. O middleware `aplicar.contexto.empresa`
    interpreta o param `?empresa_id=X` na URL e atualiza
    `session('empresa_contexto_atual')`.

    Comportamento on change:
      - Se o select estiver dentro de um <form>: submete o form (preserva
        outros filtros que o usuario tenha digitado).
      - Senao: navega para a URL atual com `?empresa_id=X` setado.

    Modos:
      - 'embed' (default quando dentro de form): renderiza como uma coluna
        Bootstrap pronta para entrar em filter forms.
      - 'standalone': renderiza com wrapper proprio (badge + label inline)
        para listagens sem filter form (Caixa, Agenda).

    Parametros:
      - permiteTodas (bool, default true): inclui a opcao "Todas as empresas".
        Use false em telas que exigem 1 empresa unica (ex: Caixa).

    Uso (standalone):    @ include partials.filtro-empresa-listagem
    Uso (embed):         @ include partials.filtro-empresa-listagem com modo=embed
    Sem "Todas":         @ include partials.filtro-empresa-listagem com permiteTodas=false
--}}
@php
    use App\Modules\Tenant\Models\Empresa;

    $empresasAtuais = collect(session('empresas_atuais', []))->map(fn ($id) => (int) $id)->all();

    if (count($empresasAtuais) <= 1) {
        return;
    }

    $modoFiltro = $modo ?? 'standalone';
    $colunaCss = $colunaCss ?? 'col-md-3';
    $permiteTodas = $permiteTodas ?? true;

    $contextoAtual = session('empresa_contexto_atual');
    $valorSelecionado = (int) request('empresa_id', $contextoAtual ?? 0);
    if (request('empresa_id') === 'todas') {
        $valorSelecionado = 0;
    }

    $opcoes = Empresa::query()
        ->whereIn('id', $empresasAtuais)
        ->orderBy('nome')
        ->get(['id', 'nome']);
@endphp

@if ($modoFiltro === 'embed')
    <div class="{{ $colunaCss }}">
        <label class="form-label">Empresa</label>
        <select name="empresa_id" class="form-select" data-filtro-empresa>
            @if ($permiteTodas)
                <option value="todas" @selected($valorSelecionado === 0)>Todas as empresas</option>
            @endif
            @foreach ($opcoes as $opcao)
                <option value="{{ $opcao->id }}" @selected($valorSelecionado === (int) $opcao->id)>
                    {{ $opcao->nome }}
                </option>
            @endforeach
        </select>
    </div>
@else
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <label class="form-label fw-semibold mb-0 text-nowrap">
            <i class="feather-briefcase me-1"></i>Empresa:
        </label>
        <select class="form-select form-select-sm" style="max-width: 260px;" data-filtro-empresa>
            @if ($permiteTodas)
                <option value="todas" @selected($valorSelecionado === 0)>Todas as empresas</option>
            @endif
            @foreach ($opcoes as $opcao)
                <option value="{{ $opcao->id }}" @selected($valorSelecionado === (int) $opcao->id)>
                    {{ $opcao->nome }}
                </option>
            @endforeach
        </select>
        @if ($contextoAtual !== null)
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                Operando em 1 empresa
            </span>
        @endif
    </div>
@endif

@once
    @push('js')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-filtro-empresa]').forEach(function (select) {
            select.addEventListener('change', function () {
                const form = select.closest('form');
                if (form) {
                    // Embedded em filter form: submete o form para preservar
                    // outros campos preenchidos pelo usuario.
                    form.submit();
                } else {
                    // Standalone: muda a URL diretamente.
                    const url = new URL(window.location.href);
                    url.searchParams.set('empresa_id', select.value);
                    window.location.assign(url.toString());
                }
            });
        });
    });
    </script>
    @endpush
@endonce
