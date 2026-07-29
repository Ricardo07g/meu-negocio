@props([
    'ver' => null,
    'editar' => null,
    'excluir' => null,
    'confirmacao' => 'Excluir este registro?',
    'permissaoEditar' => null,
    'permissaoExcluir' => null,
])

{{--
    Dropdown de acoes da linha de listagem (Ver / Editar / Excluir).

    O mesmo hstack > dropdown > ul estava copiado em 10 telas, junto com o form
    POST+DELETE e o data-confirm. Centralizar tambem garante um alvo de toque
    unico para o gatilho.

    Props (toda URL omitida some do menu):
      - ver / editar / excluir (string|null): URLs. `excluir` vira form DELETE.
      - confirmacao (string):                 texto do SweetAlert de exclusao.
      - permissaoEditar / permissaoExcluir:   slugs de gate. Omita para nao checar.

    O $slot entra antes do divisor, para acoes especificas da tela.
--}}
@php
    $usuario = auth()->user();
    $mostraEditar = $editar && (! $permissaoEditar || $usuario?->can($permissaoEditar));
    $mostraExcluir = $excluir && (! $permissaoExcluir || $usuario?->can($permissaoExcluir));
@endphp

<div class="hstack gap-2 justify-content-end">
    <div class="dropdown">
        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21" aria-label="Ações">
            <i class="feather-more-horizontal"></i>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
            @if($ver)
            <li>
                <a class="dropdown-item" href="{{ $ver }}">
                    <i class="feather-eye me-3"></i>
                    <span>Ver</span>
                </a>
            </li>
            @endif

            @if($mostraEditar)
            <li>
                <a class="dropdown-item" href="{{ $editar }}">
                    <i class="feather-edit-3 me-3"></i>
                    <span>Editar</span>
                </a>
            </li>
            @endif

            {{ $slot }}

            @if($mostraExcluir)
            <li class="dropdown-divider"></li>
            <li>
                <form action="{{ $excluir }}" method="POST" data-confirm="{{ $confirmacao }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="dropdown-item text-danger">
                        <i class="feather-trash-2 me-3"></i>
                        <span>Excluir</span>
                    </button>
                </form>
            </li>
            @endif
        </ul>
    </div>
</div>
