@props([
    'voltar' => null,
    'salvarTexto' => 'Salvar',
    'salvarIcone' => 'feather-save',
    'fixa' => true,
])

{{--
    Barra de acoes de formulario (Voltar / Salvar).

    Desktop (>= sm): dois botoes de 300px nas pontas, como sempre foi.
    Mobile  (<  sm): empilhado e full-width, com o Salvar por cima (a acao
                     primaria fica sob o polegar). No DOM o Voltar vem primeiro
                     — a ordem de leitura e de tabulacao continua Voltar ->
                     Salvar; quem inverte e o flex-direction: column-reverse.

    Props:
      - voltar (string|null): URL do "Voltar" (pede confirmacao antes de sair).
                              Omita em form curto que nao tem para onde voltar
                              (ex.: abas de Meu Perfil) — fica so o submit.
      - salvarTexto (string): rotulo do submit. Ex.: "Registrar Baixa".
      - salvarIcone (string): classe do icone Feather do submit.
      - fixa (bool):          no mobile, gruda no rodape da viewport. Ligado por
                              padrao (forms longos). Desligue quando a barra
                              estiver dentro de um card ou aba — ancestral com
                              overflow quebra position: sticky.

    O $slot entra entre os dois botoes, para acoes extras.
--}}
<div @class([
    'barra-acoes-form',
    'barra-acoes-form--fixa' => $fixa,
    'barra-acoes-form--direita' => ! $voltar,
])>
    @if($voltar)
    <button type="button" class="btn btn-light px-5 btn-acao-form" data-voltar-url="{{ $voltar }}" data-voltar-form>
        <i class="feather-arrow-left me-2"></i>Voltar
    </button>
    @endif

    {{ $slot }}

    <button type="submit" class="btn btn-primary px-5 btn-acao-form" data-submit-unico>
        <i class="{{ $salvarIcone }} me-2"></i>{{ $salvarTexto }}
    </button>
</div>

@once
@push('js')
<script>
(function () {
    // ── Voltar: confirma o descarte antes de sair do formulario ──────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-voltar-form]');
        if (! btn) return;

        e.preventDefault();
        Swal.fire({
            title: 'Deseja voltar?',
            text: 'As alterações não salvas serão perdidas.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3454d1',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, voltar',
            cancelButtonText: 'Continuar editando'
        }).then(function (result) {
            if (result.value) {
                window.location.href = btn.getAttribute('data-voltar-url');
            }
        });
    });

    // ── Anti-double-submit ────────────────────────────────────────────────
    // Nao usa `disabled` de proposito: botao desabilitado nao envia name/value.
    // Trava por atributo (o CSS aplica pointer-events: none) e so depois que o
    // evento terminou de propagar — assim validacao JS ou o SweetAlert de
    // data-confirm, que dao preventDefault, nao deixam o botao preso.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (! (form instanceof HTMLFormElement)) return;

        var btn = form.querySelector('[data-submit-unico]');
        if (! btn) return;

        if (form.dataset.submetendo === 'true') {
            e.preventDefault();
            return;
        }

        setTimeout(function () {
            if (e.defaultPrevented) return;

            form.dataset.submetendo = 'true';
            btn.setAttribute('data-submetendo', '');

            var icone = btn.querySelector('i');
            if (icone) icone.className = 'spinner-border spinner-border-sm me-2';
        }, 0);
    });
})();
</script>
@endpush
@endonce
