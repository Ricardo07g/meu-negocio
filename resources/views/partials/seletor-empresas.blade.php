{{-- Seletor multi-empresa do header (ME-009) --}}
@php
    use App\Modules\Tenant\Models\Empresa;
    $usuarioAtual = auth()->user();
    if (! $usuarioAtual) {
        return;
    }
    $empresasDisponiveis = $usuarioAtual->hasRole('Admin')
        ? Empresa::query()->orderBy('nome')->get(['id', 'nome'])
        : $usuarioAtual->empresas()->orderBy('nome')->get(['empresas.id', 'empresas.nome']);

    $selecionadas = collect(session('empresas_atuais', []))->map(fn ($id) => (int) $id);
    $totalDisponiveis = $empresasDisponiveis->count();
    $totalSelecionadas = $selecionadas->count();

    if ($totalDisponiveis === 0) {
        return;
    }

    if ($totalDisponiveis === 1) {
        $rotuloBotao = $empresasDisponiveis->first()->nome;
    } elseif ($totalSelecionadas === $totalDisponiveis) {
        $rotuloBotao = 'Todas as empresas ('.$totalDisponiveis.')';
    } elseif ($totalSelecionadas === 1) {
        $rotuloBotao = $empresasDisponiveis->firstWhere('id', $selecionadas->first())->nome ?? '1 empresa';
    } else {
        $rotuloBotao = $totalSelecionadas.' empresas selecionadas';
    }
@endphp

@if ($totalDisponiveis >= 1)
<div class="dropdown nxl-h-item me-2" id="seletorEmpresas">
    <a href="javascript:void(0);" class="nxl-head-link d-flex align-items-center gap-2"
       data-bs-toggle="dropdown" role="button"
       data-bs-auto-close="outside"
       aria-expanded="false"
       title="Empresas atuais">
        <i class="feather-home"></i>
        <span class="d-none d-md-inline fs-13 fw-medium text-dark">{{ $rotuloBotao }}</span>
        <i class="feather-chevron-down fs-12"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown p-0" style="min-width: 280px; max-width: 360px;">
        <div class="dropdown-header d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
            <span class="fw-semibold text-dark">Empresas atuais</span>
            @if ($totalDisponiveis > 1)
                <a href="javascript:void(0);" class="fs-12 text-primary" id="seletorEmpresasToggle">Marcar todas</a>
            @endif
        </div>
        <div class="px-3 py-2" style="max-height: 320px; overflow-y: auto;">
            @foreach ($empresasDisponiveis as $empresa)
                <div class="form-check mb-2">
                    <input class="form-check-input seletor-empresa-checkbox" type="checkbox"
                           value="{{ $empresa->id }}"
                           id="seletorEmpresa{{ $empresa->id }}"
                           @if ($selecionadas->contains($empresa->id)) checked @endif
                           @if ($totalDisponiveis === 1) disabled @endif>
                    <label class="form-check-label" for="seletorEmpresa{{ $empresa->id }}">
                        {{ $empresa->nome }}
                    </label>
                </div>
            @endforeach
        </div>
        @if ($totalDisponiveis > 1)
            <div class="border-top px-3 py-2 d-flex justify-content-end">
                <button type="button" class="btn btn-primary btn-sm" id="seletorEmpresasAplicar">
                    Aplicar
                </button>
            </div>
        @endif
    </div>
</div>

@if ($totalDisponiveis > 1)
<script>
(function () {
    var dropdown = document.getElementById('seletorEmpresas');
    if (!dropdown) return;

    var checkboxes = dropdown.querySelectorAll('.seletor-empresa-checkbox');
    var btnToggle = document.getElementById('seletorEmpresasToggle');
    var btnAplicar = document.getElementById('seletorEmpresasAplicar');

    if (btnToggle) {
        btnToggle.addEventListener('click', function () {
            var algumDesmarcado = Array.from(checkboxes).some(function (c) { return !c.checked; });
            checkboxes.forEach(function (c) { c.checked = algumDesmarcado; });
        });
    }

    if (btnAplicar) {
        btnAplicar.addEventListener('click', function () {
            var ids = Array.from(checkboxes).filter(function (c) { return c.checked; }).map(function (c) { return parseInt(c.value, 10); });
            if (ids.length === 0) {
                Swal.fire({ icon: 'warning', title: 'Selecione ao menos 1 empresa', confirmButtonColor: '#3454d1' });
                return;
            }

            var csrf = document.querySelector('meta[name="csrf-token"]').content;
            btnAplicar.disabled = true;

            fetch('{{ route('empresas-atuais.atualizar') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ ids: ids })
            }).then(function (r) {
                if (!r.ok) throw new Error('Falha ao atualizar empresas');
                window.location.reload();
            }).catch(function () {
                btnAplicar.disabled = false;
                Swal.fire({ icon: 'error', title: 'Erro', text: 'Nao foi possivel atualizar as empresas selecionadas.' });
            });
        });
    }
})();
</script>
@endif
@endif
