@props([
    'voltar',
    'editar' => null,
    'editarTexto' => 'Editar',
])

<div class="d-flex {{ $editar ? 'justify-content-between' : 'justify-content-start' }} mb-5 pb-4">
    <a href="{{ $voltar }}" class="btn btn-light px-5" style="min-width: 300px;">
        <i class="feather-arrow-left me-2"></i>Voltar
    </a>
    @if($editar)
    <a href="{{ $editar }}" class="btn btn-primary px-5" style="min-width: 300px;">
        <i class="feather-edit me-2"></i>{{ $editarTexto }}
    </a>
    @endif
</div>
