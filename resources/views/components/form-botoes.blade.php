@props([
    'voltar',
    'salvarTexto' => 'Salvar',
])

<div class="d-flex justify-content-between mb-5 pb-4">
    <button type="button" class="btn btn-light px-5" style="min-width: 300px;" data-voltar-url="{{ $voltar }}" onclick="confirmarVoltarForm(this)">
        <i class="feather-arrow-left me-2"></i>Voltar
    </button>
    <button type="submit" class="btn btn-primary px-5" style="min-width: 300px;">
        <i class="feather-save me-2"></i>{{ $salvarTexto }}
    </button>
</div>

<script>
    function confirmarVoltarForm(btn) {
        var url = btn.getAttribute('data-voltar-url');
        Swal.fire({
            title: 'Deseja voltar?',
            text: 'As alterações não salvas serão perdidas.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3454d1',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, voltar',
            cancelButtonText: 'Continuar editando'
        }).then(function(result) {
            if (result.value) {
                window.location.href = url;
            }
        });
    }
</script>
