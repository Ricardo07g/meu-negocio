@props([
    'rota',
    'label',
    'icone' => 'feather-plus',
    'permissao' => null,
])

{{--
    Botao "Novo X" do topo das listagens.

    O bloco row > col > a.btn.w-100 estava copiado em 10 index.blade.php, cada um
    com seu proprio @can em volta. Centralizar aqui deixa o breakpoint num lugar
    so — quando a largura precisar mudar, muda aqui.

    Props:
      - rota (string):       destino (normalmente route('x.create')).
      - label (string):      rotulo. Ex.: "Novo Cliente".
      - icone (string):      classe do icone Feather.
      - permissao (?string): slug do gate. Omita para renderizar sem checar.
--}}
@if(! $permissao || auth()->user()?->can($permissao))
<div class="row mb-4">
    <div class="col-xxl-3 col-md-6">
        <a href="{{ $rota }}" class="btn btn-primary w-100">
            <i class="{{ $icone }} me-2"></i>{{ $label }}
        </a>
    </div>
</div>
@endif
