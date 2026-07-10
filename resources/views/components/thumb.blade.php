@props([
    'url' => null,
    'nome' => '',
    'icone' => null,
    'classe' => 'avatar-md',
    'circulo' => true,
])

@php $raio = $circulo ? 'rounded-circle' : 'rounded'; @endphp

@if($url)
    <img src="{{ $url }}" alt="{{ $nome }}" class="{{ $classe }} {{ $raio }}" style="object-fit:cover;">
@else
    <span class="avatar-text {{ $classe }} {{ $raio }} bg-primary text-white">
        @if($icone)
            <i class="{{ $icone }}"></i>
        @else
            {{ mb_strtoupper(mb_substr($nome, 0, 1)) }}
        @endif
    </span>
@endif
