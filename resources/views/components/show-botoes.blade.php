@props([
    'voltar',
    'editar' => null,
    'editarTexto' => 'Editar',
    'editarIcone' => 'feather-edit',
])

{{--
    Barra de acoes de tela de detalhe (Voltar / Editar).

    Mesma mecanica responsiva do <x-form-botoes>, mas sem o modificador --fixa:
    numa tela de leitura nao ha nada a perder ao rolar, entao a barra nao
    precisa ocupar o rodape permanentemente. No mobile os botoes empilham e
    ficam full-width; no desktop voltam aos 300px nas pontas.

    O $slot entra entre os dois botoes, para acoes extras (imprimir, estornar...).
--}}
<div class="barra-acoes-form {{ $editar ? '' : 'barra-acoes-form--unico' }}">
    <a href="{{ $voltar }}" class="btn btn-light px-5 btn-acao-form">
        <i class="feather-arrow-left me-2"></i>Voltar
    </a>

    {{ $slot }}

    @if($editar)
    <a href="{{ $editar }}" class="btn btn-primary px-5 btn-acao-form">
        <i class="{{ $editarIcone }} me-2"></i>{{ $editarTexto }}
    </a>
    @endif
</div>
