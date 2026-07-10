@props([
    'atual' => null,
    'nome' => 'foto',
    'label' => 'Foto',
    'formato' => 'circulo', // circulo | quadrado
])

@php
    $raio = $formato === 'circulo' ? 'ci-circ' : 'ci-quad';
    $inputId = $nome.'-'.uniqid();
@endphp

<div class="campo-imagem" data-campo-imagem>
    <label class="form-label d-block" for="{{ $inputId }}">{{ $label }}</label>

    <div class="d-flex align-items-center gap-3">
        <div class="ci-well {{ $raio }} @error($nome) is-invalid @enderror"
             role="button" tabindex="0"
             aria-label="{{ $label }}: escolher imagem"
             data-ci-well>
            <img data-ci-preview class="ci-img" src="{{ $atual ?: '' }}" alt=""
                 @unless($atual) hidden @endunless>
            <span data-ci-placeholder class="ci-placeholder" @if($atual) hidden @endif>
                <i class="feather-camera"></i>
            </span>
            <span class="ci-overlay" aria-hidden="true">
                <i class="feather-camera"></i>
                <span class="ci-overlay-txt" data-ci-overlay-txt>{{ $atual ? 'Alterar' : 'Enviar' }}</span>
            </span>
            <button type="button" class="ci-remove" data-ci-remove title="Remover imagem"
                    aria-label="Remover imagem" @unless($atual) hidden @endunless>
                <i class="feather-x"></i>
            </button>
        </div>

        <div class="ci-meta">
            <input type="file" id="{{ $inputId }}" name="{{ $nome }}" accept="image/jpeg,image/png,image/webp"
                   class="d-none" data-ci-input>
            <p class="ci-hint mb-1">Clique no círculo ou arraste uma imagem.</p>
            <p class="ci-sub mb-0">JPG, PNG ou WEBP · até 2&nbsp;MB.</p>
            @error($nome) <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
            @if($atual)
                <input type="hidden" name="remover_{{ $nome }}" value="0" data-ci-remove-flag>
            @endif
        </div>
    </div>
</div>

@once
    @push('css')
        <style>
            .ci-well {
                position: relative;
                width: 112px; height: 112px;
                flex: 0 0 auto;
                display: grid; place-items: center;
                background: var(--bs-tertiary-bg, #f6f7f9);
                border: 2px dashed var(--bs-border-color, #dbe0e5);
                color: var(--bs-secondary-color, #8a94a6);
                cursor: pointer;
                overflow: hidden;
                transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
            }
            .ci-well.ci-circ { border-radius: 50%; }
            .ci-well.ci-quad { border-radius: 14px; }
            .ci-well:hover, .ci-well:focus-visible { border-color: #3454d1; outline: none; }
            .ci-well:focus-visible { box-shadow: 0 0 0 3px rgba(52,84,209,.25); }
            .ci-well.ci-drag { border-style: solid; border-color: #3454d1; background: rgba(52,84,209,.06); }
            .ci-well.is-invalid { border-color: var(--bs-danger, #dc3545); }

            .ci-img { width: 100%; height: 100%; object-fit: cover; grid-area: 1 / 1; }
            .ci-placeholder { grid-area: 1 / 1; font-size: 26px; line-height: 0; }

            .ci-overlay {
                grid-area: 1 / 1;
                display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px;
                background: rgba(15, 23, 42, .55);
                color: #fff; font-size: 20px;
                opacity: 0; transition: opacity .15s ease;
            }
            .ci-overlay-txt { font-size: 11px; font-weight: 600; letter-spacing: .02em; }
            .ci-well:hover .ci-overlay, .ci-well:focus-visible .ci-overlay { opacity: 1; }

            .ci-remove {
                position: absolute; top: 4px; right: 4px; z-index: 2;
                width: 24px; height: 24px; padding: 0;
                display: inline-flex; align-items: center; justify-content: center;
                border: 0; border-radius: 50%;
                background: var(--bs-danger, #dc3545); color: #fff; font-size: 13px;
                box-shadow: 0 1px 4px rgba(0,0,0,.3);
                opacity: 0; transform: scale(.85); transition: opacity .15s ease, transform .15s ease;
            }
            .ci-well:hover .ci-remove:not([hidden]),
            .ci-well:focus-within .ci-remove:not([hidden]) { opacity: 1; transform: scale(1); }
            .ci-remove:hover { filter: brightness(1.08); }

            .ci-hint { font-size: 13px; font-weight: 500; color: var(--bs-body-color, #3b4453); }
            .ci-sub { font-size: 12px; color: var(--bs-secondary-color, #8a94a6); }

            @media (prefers-reduced-motion: reduce) {
                .ci-well, .ci-overlay, .ci-remove { transition: none; }
            }
        </style>
    @endpush

    @push('js')
        <script>
            document.querySelectorAll('[data-campo-imagem]').forEach(function (raiz) {
                var well = raiz.querySelector('[data-ci-well]');
                var input = raiz.querySelector('[data-ci-input]');
                var preview = raiz.querySelector('[data-ci-preview]');
                var placeholder = raiz.querySelector('[data-ci-placeholder]');
                var remover = raiz.querySelector('[data-ci-remove]');
                var overlayTxt = raiz.querySelector('[data-ci-overlay-txt]');
                var flag = raiz.querySelector('[data-ci-remove-flag]');

                function mostrar(url) {
                    preview.src = url;
                    preview.hidden = false;
                    placeholder.hidden = true;
                    remover.hidden = false;
                    overlayTxt.textContent = 'Alterar';
                    if (flag) flag.value = '0';
                }

                function limpar() {
                    input.value = '';
                    preview.removeAttribute('src');
                    preview.hidden = true;
                    placeholder.hidden = false;
                    remover.hidden = true;
                    overlayTxt.textContent = 'Enviar';
                    if (flag) flag.value = '1'; // remove a imagem existente ao salvar
                }

                well.addEventListener('click', function () { input.click(); });
                well.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
                });

                input.addEventListener('change', function () {
                    var f = input.files && input.files[0];
                    if (f) mostrar(URL.createObjectURL(f));
                });

                remover.addEventListener('click', function (e) {
                    e.stopPropagation();
                    limpar();
                });

                ['dragenter', 'dragover'].forEach(function (ev) {
                    well.addEventListener(ev, function (e) { e.preventDefault(); well.classList.add('ci-drag'); });
                });
                ['dragleave', 'dragend', 'drop'].forEach(function (ev) {
                    well.addEventListener(ev, function () { well.classList.remove('ci-drag'); });
                });
                well.addEventListener('drop', function (e) {
                    e.preventDefault();
                    if (e.dataTransfer.files && e.dataTransfer.files.length) {
                        input.files = e.dataTransfer.files;
                        input.dispatchEvent(new Event('change'));
                    }
                });
            });
        </script>
    @endpush
@endonce
