@props(['action', 'ativo' => false])

{{--
    Envelope compartilhado de filtros de listagem (card + form GET + botoes Filtrar/Limpar).
    Colapsa no mobile atras de um botao "Filtros"; sempre visivel no desktop (>= md).

    Props:
      - action (string): rota do index; alimenta o <form> E o botao "Limpar".
      - ativo (bool):    ha filtros aplicados? Abre o bloco no mobile e destaca o botao.

    Os campos entram via {{ '{{ $slot }}' }} (cada listagem define os seus). Use classes de
    coluna responsivas nos campos (ex.: col-12 col-sm-6 col-md-3) para 2-por-linha no tablet.
--}}
<div class="card stretch stretch-full mb-4">
    <div class="card-body">
        {{-- Cabecalho mobile: botao mostrar/ocultar filtros (so < md) --}}
        <div class="d-flex d-md-none justify-content-between align-items-center">
            <span class="fw-semibold">
                <i class="feather-filter me-1"></i>Filtros
                @if ($ativo)
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">ativos</span>
                @endif
            </span>
            <button class="btn btn-sm btn-light" type="button"
                    data-bs-toggle="collapse" data-bs-target="#filtrosListagemColapse"
                    aria-expanded="{{ $ativo ? 'true' : 'false' }}" aria-controls="filtrosListagemColapse">
                <i class="feather-sliders me-1"></i>{{ $ativo ? 'Ajustar' : 'Mostrar' }}
            </button>
        </div>

        <form method="GET" action="{{ $action }}">
            <div class="collapse d-md-block mt-3 mt-md-0 {{ $ativo ? 'show' : '' }}" id="filtrosListagemColapse">
                <div class="row g-3 align-items-end">
                    {{ $slot }}

                    <div class="col-12 d-flex flex-column flex-sm-row justify-content-sm-end gap-2">
                        <a href="{{ $action }}" class="btn btn-light" title="Limpar filtros">
                            <i class="feather-x me-1"></i>Limpar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-filter me-1"></i>Filtrar
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
