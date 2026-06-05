@php
    $perfilAcesso = $perfilAcesso ?? null;
    $perfilPermissoes = $perfilPermissoes ?? [];
    $isAdmin = $perfilAcesso && $perfilAcesso->name === 'Admin';
@endphp

<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">{{ $perfilAcesso ? 'Editar Perfil de Acesso' : 'Cadastrar Perfil de Acesso' }}</h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label">Nome do Perfil <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $perfilAcesso->name ?? '') }}" required {{ $isAdmin ? 'readonly' : '' }}>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <h6 class="fw-bold mb-3">Permissões</h6>

        @php $marcadas = collect(old('permissoes', $perfilPermissoes)); @endphp

        @foreach ($permissoes as $modulo => $info)
            @php
                $totalPerm = $info['permissoes']->count();
                $selecionadas = $info['permissoes']->pluck('name')->intersect($marcadas)->count();
                $algumaSelecionada = $selecionadas > 0;
                $todasSelecionadas = $selecionadas === $totalPerm;
                $collapseId = 'perm-modulo-'.$modulo;
            @endphp

            <div class="card border mb-3">
                <div class="card-header py-2 px-3 bg-light js-collapse-toggle"
                     style="cursor:pointer;"
                     data-collapse-target="#{{ $collapseId }}"
                     aria-expanded="true"
                     aria-controls="{{ $collapseId }}">
                    <div class="d-flex align-items-center justify-content-between w-100">
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input mt-0"
                                   type="checkbox"
                                   id="master-{{ $modulo }}"
                                   data-master-modulo="{{ $modulo }}"
                                   title="Marcar/desmarcar todas as permissões deste módulo"
                                   @checked($todasSelecionadas)>
                            <i class="{{ $info['icone'] }} fs-18 text-primary"></i>
                            <span class="fw-semibold">{{ $info['label'] }}</span>
                            <span class="badge bg-soft-primary text-primary ms-2"
                                  data-contador-modulo="{{ $modulo }}">
                                {{ $selecionadas }}/{{ $totalPerm }}
                            </span>
                        </div>
                        <i class="feather-chevron-down fs-14 text-muted js-collapse-chevron"></i>
                    </div>
                </div>
                <div class="collapse show" id="{{ $collapseId }}">
                    <div class="card-body py-3">
                        <div class="row g-2">
                            @foreach ($info['permissoes'] as $perm)
                                <div class="col-md-3 col-sm-6 col-12">
                                    <div class="form-check">
                                        <input type="checkbox"
                                               name="permissoes[]"
                                               value="{{ $perm['name'] }}"
                                               class="form-check-input"
                                               data-modulo="{{ $modulo }}"
                                               id="perm_{{ $perm['id'] }}"
                                               @checked($marcadas->contains($perm['name']))>
                                        <label class="form-check-label" for="perm_{{ $perm['id'] }}">
                                            {{ $perm['rotulo'] }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<x-form-botoes :voltar="route('perfis-acesso.index')" />

@push('css')
<style>
    /* Chevron acompanha estado de expansao do header. */
    .js-collapse-toggle .js-collapse-chevron {
        transition: transform .2s ease;
    }
    .js-collapse-toggle[aria-expanded="false"] .js-collapse-chevron {
        transform: rotate(-90deg);
    }
</style>
@endpush

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-collapse-toggle[data-collapse-target]').forEach(function (header) {
        const collapseEl = document.querySelector(header.getAttribute('data-collapse-target'));
        if (! collapseEl) return;

        header.addEventListener('click', function (event) {
            if (event.target.closest('input[data-master-modulo]')) return;
            bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).toggle();
        });

        collapseEl.addEventListener('shown.bs.collapse', function () {
            header.setAttribute('aria-expanded', 'true');
        });
        collapseEl.addEventListener('hidden.bs.collapse', function () {
            header.setAttribute('aria-expanded', 'false');
        });
    });

    document.querySelectorAll('input[data-master-modulo]').forEach(function (master) {
        const modulo = master.dataset.masterModulo;
        const filhos = document.querySelectorAll('input[data-modulo="' + modulo + '"]');
        const contador = document.querySelector('[data-contador-modulo="' + modulo + '"]');
        const collapseEl = document.getElementById('perm-modulo-' + modulo);

        function atualizar() {
            const marcadas = Array.from(filhos).filter(function (c) { return c.checked; }).length;
            const total = filhos.length;
            if (contador) contador.textContent = marcadas + '/' + total;
            master.checked = total > 0 && marcadas === total;
            master.indeterminate = marcadas > 0 && marcadas < total;
        }

        function expandirSeColapsado() {
            if (! collapseEl || collapseEl.classList.contains('show')) return;
            // Cria instancia sem auto-toggle e abre programaticamente.
            bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
        }

        master.addEventListener('change', function () {
            filhos.forEach(function (c) { c.checked = master.checked; });
            atualizar();
            expandirSeColapsado();
        });

        filhos.forEach(function (c) {
            c.addEventListener('change', atualizar);
        });

        atualizar();
    });
});
</script>
@endpush
