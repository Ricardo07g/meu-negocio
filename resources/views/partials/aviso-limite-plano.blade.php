{{--
    Aviso visual de limite de plano + botao "novo" desabilitado quando atingido.

    Uso:
        @include('partials.aviso-limite-plano', [
            'recurso'     => 'empresas',         // label plural exibido
            'atual'       => $limite['atual'],
            'maximo'      => $limite['maximo'],  // 0 = ilimitado
            'atingido'    => $limite['atingido'],
            'rotaCriar'   => route('empresas.create'),
            'labelBotao'  => 'Nova Empresa',
            'permissaoBlade' => 'empresa.criar',
        ])
--}}
@php
    $atingido = $atingido ?? false;
    $atual = $atual ?? 0;
    $maximo = $maximo ?? 0;
    $ilimitado = $maximo === 0;
    $proximoLimite = ! $ilimitado && ! $atingido && $atual >= ($maximo - 1);
@endphp

@php
    if ($ilimitado) {
        $alertClass = 'alert-light border';
        $iconClass = 'feather-info';
        $badgeClass = 'bg-success-subtle text-success';
        $badgeTexto = $atual.' cadastrados';
    } elseif ($atingido) {
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
        @if ($ilimitado)
            Voce esta no plano com <strong>{{ ucfirst($recurso) }} ilimitados</strong>. {{ $atual }} cadastrados ate o momento.
        @elseif ($atingido)
            <strong>Limite do plano atingido.</strong>
            Voce ja cadastrou {{ $atual }} de {{ $maximo }} {{ $recurso }} permitidos.
            Para cadastrar mais, fale com o suporte para ampliar o plano.
        @else
            Em uso: <strong>{{ $atual }}</strong> de <strong>{{ $maximo }}</strong> {{ $recurso }} disponiveis no seu plano.
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
