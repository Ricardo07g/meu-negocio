{{--
    Aviso visual de limite da licenca + botao "novo" desabilitado quando atingido.

    Todo limite e finito: nao existe mais a convencao `0 = ilimitado`.

    Uso:
        @include('partials.aviso-limite-plano', [
            'recurso'     => 'usuarios',         // label plural exibido
            'atual'       => $limite['atual'],
            'maximo'      => $limite['maximo'],
            'atingido'    => $limite['atingido'],
            'rotaCriar'   => route('usuarios.create'),
            'labelBotao'  => 'Novo Usuario',
            'permissaoBlade' => 'usuario.criar',
        ])
--}}
@php
    $atingido = $atingido ?? false;
    $atual = $atual ?? 0;
    $maximo = $maximo ?? 0;
    $proximoLimite = ! $atingido && $atual >= ($maximo - 1);
@endphp

@php
    if ($atingido) {
        $alertClass = 'alert-warning';
        $iconClass = 'feather-alert-triangle';
        $badgeClass = 'bg-warning';
        $badgeTexto = $atual.' / '.$maximo;
    } elseif ($proximoLimite) {
        $alertClass = 'alert-info';
        $iconClass = 'feather-info';
        $badgeClass = 'bg-info';
        $badgeTexto = $atual.' / '.$maximo;
    } else {
        $alertClass = 'alert-light border';
        $iconClass = 'feather-info';
        $badgeClass = 'bg-secondary';
        $badgeTexto = $atual.' / '.$maximo;
    }
@endphp

<div class="alert {{ $alertClass }} d-flex align-items-center mb-3" role="alert">
    <i class="{{ $iconClass }} me-2"></i>
    <div class="flex-grow-1">
        @if ($atingido)
            <strong>Limite da licenca atingido.</strong>
            Voce ja cadastrou {{ $atual }} de {{ $maximo }} {{ $recurso }} permitidos nesta unidade.
            Para cadastrar mais, fale com o suporte para ampliar a licenca.
        @else
            Em uso: <strong>{{ $atual }}</strong> de <strong>{{ $maximo }}</strong> {{ $recurso }} disponiveis na licenca desta unidade.
        @endif
    </div>
    <span class="badge {{ $badgeClass }}">{{ $badgeTexto }}</span>
</div>

@can($permissaoBlade)
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            @if ($atingido)
                <button type="button" class="btn btn-primary w-100" disabled
                        title="Limite do plano atingido — entre em contato com o suporte para ampliar.">
                    <i class="feather-plus me-2"></i>{{ $labelBotao }}
                </button>
            @else
                <a href="{{ $rotaCriar }}" class="btn btn-primary w-100">
                    <i class="feather-plus me-2"></i>{{ $labelBotao }}
                </a>
            @endif
        </div>
    </div>
@endcan
