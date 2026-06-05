{{--
    Monograma "MN" da marca Meu Negócio.
    Uso: @include('partials.logo-mark', ['size' => 38])
    O id do gradiente é único por inclusão para evitar colisão quando o mark
    aparece mais de uma vez na mesma página (sidebar expandida + recolhida, etc).
--}}
@php
    $size = $size ?? 38;
    $gid = 'mnGrad_' . substr(md5(uniqid('', true)), 0, 8);
@endphp
<svg class="mn-logo-mark" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <defs>
        <linearGradient id="{{ $gid }}" x1="2" y1="2" x2="46" y2="46" gradientUnits="userSpaceOnUse">
            <stop stop-color="#3f5fe0"/>
            <stop offset="1" stop-color="#2740b4"/>
        </linearGradient>
    </defs>
    <rect width="48" height="48" rx="13" fill="url(#{{ $gid }})"/>
    <rect x="0.6" y="0.6" width="46.8" height="46.8" rx="12.4" fill="none" stroke="#fff" stroke-opacity="0.12" stroke-width="1.2"/>
    <g stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" fill="none">
        <path d="M9 34 L9 14 L16 26 L23 14 L23 34"/>
        <path d="M27 34 L27 14 L39 34 L39 14"/>
    </g>
</svg>
