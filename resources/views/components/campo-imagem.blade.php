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

<div class="campo-imagem" data-campo-imagem data-formato="{{ $formato }}">
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
        </div>

        <div class="ci-meta">
            <input type="file" id="{{ $inputId }}" name="{{ $nome }}" accept="image/jpeg,image/png,image/webp"
                   class="d-none" data-ci-input>

            <div class="ci-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-ci-change>
                    <i class="feather-edit-2 me-1"></i><span data-ci-change-txt>{{ $atual ? 'Alterar' : 'Enviar' }}</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" data-ci-remove
                        @unless($atual) hidden @endunless>
                    <i class="feather-trash-2 me-1"></i>Remover
                </button>
            </div>

            <p class="ci-hint mb-0 mt-2">Clique ou arraste uma imagem — você poderá recortá-la.</p>
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

            .ci-actions { display: flex; flex-wrap: wrap; gap: 8px; }
            .ci-hint { font-size: 13px; font-weight: 500; color: var(--bs-body-color, #3b4453); }
            .ci-sub { font-size: 12px; color: var(--bs-secondary-color, #8a94a6); }

            /* Modal de recorte (Cropper.js) */
            .ci-crop-stage { max-height: 60vh; }
            .ci-crop-stage img { display: block; max-width: 100%; max-height: 58vh; }
            .ci-crop-tools { display: flex; flex-wrap: wrap; gap: 6px; justify-content: center; margin-top: 14px; }
            .ci-crop-stage.is-round .cropper-view-box,
            .ci-crop-stage.is-round .cropper-face { border-radius: 50%; }

            @media (prefers-reduced-motion: reduce) {
                .ci-well { transition: none; }
            }
        </style>
    @endpush

    @push('js')
        @vite('resources/js/campo-imagem.js')
    @endpush
@endonce
