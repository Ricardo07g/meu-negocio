@props([
    'atual' => null,
    'nome' => 'foto',
    'label' => 'Foto',
])

<div class="mb-0">
    <label class="form-label">{{ $label }}</label>
    <div class="d-flex align-items-center gap-3">
        <img data-preview
             src="{{ $atual ?: '' }}"
             class="rounded"
             style="width:76px;height:76px;object-fit:cover;{{ $atual ? '' : 'display:none;' }}"
             alt="">
        <span data-placeholder
              class="d-inline-flex align-items-center justify-content-center rounded bg-secondary-subtle text-secondary"
              style="width:76px;height:76px;{{ $atual ? 'display:none;' : '' }}">
            <i class="feather-image fs-3"></i>
        </span>
        <div class="flex-grow-1">
            <input type="file" name="{{ $nome }}" accept="image/*"
                   class="form-control @error($nome) is-invalid @enderror" data-input>
            @error($nome) <div class="invalid-feedback">{{ $message }}</div> @enderror
            <small class="text-muted">JPG, PNG ou WEBP · até 2 MB.</small>
            @if($atual)
                <div class="form-check mt-2">
                    <input type="hidden" name="remover_{{ $nome }}" value="0">
                    <input type="checkbox" name="remover_{{ $nome }}" value="1" class="form-check-input" id="remover_{{ $nome }}">
                    <label class="form-check-label" for="remover_{{ $nome }}">Remover imagem atual</label>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    (function () {
        var root = document.currentScript.previousElementSibling;
        var input = root.querySelector('[data-input]');
        var prev = root.querySelector('[data-preview]');
        var ph = root.querySelector('[data-placeholder]');
        input.addEventListener('change', function () {
            var f = input.files[0];
            if (!f) return;
            prev.src = URL.createObjectURL(f);
            prev.style.display = '';
            if (ph) ph.style.display = 'none';
        });
    })();
</script>
